#!/usr/bin/env python3
"""
Reconstrói o histórico do Controle de Fretes a partir das planilhas mensais CONTROLE_FRETE de um ano,
sem duplicar dados. Gera (sem gravar em banco nenhum):
  - importacao.sql            refaz fretes, faturas e movimentos de crédito; cadastros só ganham nomes novos
  - relatorio_importacao.xlsx resumo por planilha, ajustes de saldo, datas corrigidas, pendências e conferência
  - saldos_esperados.csv      saldo final por vendedor (para conferir com vw_saldo_vendedor depois de aplicar)

Regras:
  * Cada planilha mensal começa com linhas "CREDITO" = saldo trazido do mês anterior. Só o da primeira planilha
    vira saldo inicial; nos meses seguintes a diferença entre esse saldo e o calculado vira um movimento
    "ajuste" (visível no extrato), de forma que o saldo bate com a planilha em todo início de mês.
  * CREDITO com texto/observação (ex.: "CREDITO EMPRESTIMO") e RETIRADA sem par são movimentos do mês.
    RETIRADA + CREDITO do mesmo valor e data = transferência entre vendedores.
  * Fretes repetidos entre planilhas (mesma NF, data, valor e transportadora) entram uma vez.
  * Data depois do fim do mês da planilha (ou de outro ano) é digitação errada: usa o mês da planilha e guarda
    a data original na observação.
  * Cadastros (empresas, transportadoras, gestores, vendedores) não são apagados nem alterados: nomes novos
    são acrescentados (vendedor/gestor que só aparece em planilhas antigas entra inativo).

Uso:  python tools/importar_planilhas.py <pasta_planilhas> <pasta_saida> [banco] [ano]
"""
import calendar
import datetime as dt
import re
import sys
import unicodedata
import warnings
from collections import defaultdict
from pathlib import Path

import pandas as pd

warnings.filterwarnings("ignore")

PASTA = Path(sys.argv[1])
SAIDA = Path(sys.argv[2])
BANCO = sys.argv[3] if len(sys.argv) > 3 else "fabreck_fretes"
ANO = int(sys.argv[4]) if len(sys.argv) > 4 else 2026
SAIDA.mkdir(parents=True, exist_ok=True)

MESES = ["JANEIRO", "FEVEREIRO", "MARCO", "ABRIL", "MAIO", "JUNHO", "JULHO", "AGOSTO",
         "SETEMBRO", "OUTUBRO", "NOVEMBRO", "DEZEMBRO"]
UFS = set("AC AL AM AP BA CE DF ES GO MA MG MS MT PA PB PE PI PR RJ RN RO RR RS SC SE SP TO".split())
SEM_TRANSP = "NAO INFORMADA"

pend = []          # (planilha, linha, tipo, detalhe)
datas_corrigidas = []
ajustes_rel = []


def norm(s):
    s = unicodedata.normalize("NFKD", str(s)).encode("ascii", "ignore").decode()
    return re.sub(r"\s+", " ", s).strip().upper()


def nome(x):
    if x is None or (not isinstance(x, str) and pd.isna(x)):
        return None
    s = re.sub(r"\s+", " ", str(x)).strip().upper()
    return s or None


def doc(x):
    if x is None or (not isinstance(x, str) and pd.isna(x)):
        return None
    if isinstance(x, float) and x.is_integer():
        x = int(x)
    s = re.sub(r"\s+", " ", str(x)).strip().rstrip(".;-").strip().upper()
    return s[:60] or None


def num(x):
    v = pd.to_numeric(x, errors="coerce")
    return None if pd.isna(v) else round(float(v), 2)


def texto(x):
    if x is None or (not isinstance(x, str) and pd.isna(x)):
        return None
    s = re.sub(r"\s+", " ", str(x)).strip()
    return s or None


def q(v):
    if v is None or v is pd.NaT or (isinstance(v, float) and pd.isna(v)):
        return "NULL"
    if isinstance(v, bool):
        return "1" if v else "0"
    if isinstance(v, (int, float)):
        return repr(round(v, 2) if isinstance(v, float) else v)
    if isinstance(v, (dt.date, dt.datetime, pd.Timestamp)):
        return "'" + pd.Timestamp(v).strftime("%Y-%m-%d") + "'"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"


def mes_do_arquivo(p):
    n = norm(p.name)
    for i, m in enumerate(MESES):
        if m in n:
            return i + 1
    raise SystemExit(f"Não achei o mês no nome do arquivo: {p.name}")


def achar_cabecalho(arq, aba, termo):
    bruto = pd.read_excel(arq, aba, header=None, nrows=25)
    for i in range(len(bruto)):
        if any(termo in norm(c) for c in bruto.iloc[i] if pd.notna(c)):
            return i
    raise SystemExit(f"{arq.name}: cabeçalho '{termo}' não encontrado na aba {aba}")


def mapear(colunas, regras):
    """regras: {destino: função(nome_normalizado) -> bool}; a 1ª coluna que casar fica com o destino."""
    m = {}
    for c in colunas:
        n = norm(c)
        for destino, teste in regras.items():
            if destino not in m.values() and teste(n):
                m[c] = destino
                break
    return m


def aba_validacao(xl):
    return next(a for a in xl.sheet_names if norm(a).startswith("VALIDA"))


# ------------------------------------------------------------------ leitura
planilhas = sorted(PASTA.glob("*.xlsx"), key=mes_do_arquivo)
REGRAS_VAL = {
    "NF": lambda n: "NOTA FISCAL" in n,
    "VC": lambda n: "COB CLIENTE" in n,
    "VT": lambda n: "COB TRANSP" in n,
    "UF": lambda n: n == "UF",
    "Gestor": lambda n: n == "GESTOR",
    "Vendedor": lambda n: n == "VENDEDOR",
    "Empresa": lambda n: "FABRECK" in n,
    "Transp": lambda n: "TRASNPORTADORA" in n or "TRANSPORTADORA" in n,
    "Doc": lambda n: "DOCUMENTO" in n,
    "Obs": lambda n: n == "OBS",
}
REGRAS_BOL = {
    "DATA": lambda n: n == "DATA",
    "Transp": lambda n: "TRANSPORTADORA" in n,
    "Doc": lambda n: re.fullmatch(r"N\W?O?\s*FATURA", n) is not None,
    "Empresa": lambda n: "EMPRESA" in n,
    "Venc": lambda n: "VENCIMENTO" in n,
    "Envio": lambda n: "PASSEI" in n,
    "Resp": lambda n: "RESPONSAVEL" in n,
    "RCTO": lambda n: n == "RCTO",
    "Obs": lambda n: n.startswith("OBSERVA"),
}

linhas = []      # registros da aba Valores de todas as planilhas
boletos = []     # registros da aba Validação
resumo = []
for ordem, arq in enumerate(planilhas):
    mes = mes_do_arquivo(arq)
    rotulo = f"{MESES[mes - 1].capitalize()}/{ANO}"
    hl = achar_cabecalho(arq, "Valores", "NOTA FISCAL")
    df = pd.read_excel(arq, "Valores", header=hl)
    m = mapear(df.columns, REGRAS_VAL)
    # a data é sempre a coluna antes da "Nº Nota Fiscal" que não seja X3 (o nome varia: DATA ou NF)
    col_nf = next(c for c, d in m.items() if d == "NF")
    idx_nf = list(df.columns).index(col_nf)
    col_data = next(c for c in reversed(list(df.columns)[:idx_nf]) if norm(c) not in ("X3",))
    m[col_data] = "DATA"
    df = df.rename(columns=m)
    df["linha"] = df.index + hl + 2
    df = df[df["NF"].notna()]
    fim_mes = dt.date(ANO, mes, calendar.monthrange(ANO, mes)[1])
    for _, r in df.iterrows():
        linhas.append(dict(planilha=arq.name, rotulo=rotulo, ordem=ordem, mes=mes, fim_mes=fim_mes, linha=int(r["linha"]),
                           data_raw=r.get("DATA"), nf_raw=r["NF"], vc=num(r.get("VC")), vt=num(r.get("VT")),
                           uf=nome(r.get("UF")), gestor=nome(r.get("Gestor")), vendedor=nome(r.get("Vendedor")),
                           empresa=nome(r.get("Empresa")), transp=nome(r.get("Transp")), doc=doc(r.get("Doc")),
                           obs=texto(r.get("Obs"))))
    xl = pd.ExcelFile(arq)
    ab = aba_validacao(xl)
    hb = achar_cabecalho(arq, ab, "FATURA")
    bo = pd.read_excel(arq, ab, header=hb)
    bo = bo.rename(columns=mapear(bo.columns, REGRAS_BOL))
    bo["linha"] = bo.index + hb + 2
    bo = bo[bo["Doc"].notna()] if "Doc" in bo else bo.iloc[0:0]
    for _, b in bo.iterrows():
        boletos.append(dict(planilha=arq.name, ordem=ordem, mes=mes, linha=int(b["linha"]), doc=doc(b.get("Doc")),
                            transp=nome(b.get("Transp")), empresa=nome(b.get("Empresa")),
                            data=pd.to_datetime(b.get("DATA"), errors="coerce"),
                            venc=pd.to_datetime(b.get("Venc"), errors="coerce"),
                            envio=pd.to_datetime(b.get("Envio"), errors="coerce"),
                            resp=nome(b.get("Resp")), rcto=b.get("RCTO"), obs=texto(b.get("Obs"))))
    resumo.append(dict(planilha=arq.name, mes=rotulo, linhas_valores=len(df), faturas_validacao=len(bo)))

# ------------------------------------------------------------------ datas e NF


def ajustar_data(r):
    raw = r["data_raw"]
    d = pd.to_datetime(raw, errors="coerce", dayfirst=True) if not isinstance(raw, (int, float)) or pd.isna(raw) else pd.NaT
    if isinstance(raw, (dt.datetime, pd.Timestamp)):
        d = pd.Timestamp(raw)
    if pd.isna(d):
        pend.append((r["planilha"], r["linha"], "data_vazia", f"NF {r['nf_raw']}: usado dia 1 do mês da planilha"))
        return dt.date(ANO, r["mes"], 1)
    d = d.date()
    dezembro_anterior = (r["mes"] == 1 and d.year == ANO - 1 and d.month == 12)
    if d > r["fim_mes"] or (d.year != ANO and not dezembro_anterior):
        dia = min(d.day, calendar.monthrange(ANO, r["mes"])[1])
        novo = dt.date(ANO, r["mes"], dia)
        datas_corrigidas.append(dict(planilha=r["planilha"], linha=r["linha"], nf=str(r["nf_raw"]),
                                     data_planilha=d.strftime("%d/%m/%Y"), data_usada=novo.strftime("%d/%m/%Y")))
        r["obs"] = ((r["obs"] + " | ") if r["obs"] else "") + f"Data na planilha: {d:%d/%m/%Y}"
        return novo
    return d


for r in linhas:
    r["data"] = ajustar_data(r)
    raw = r["nf_raw"]
    if isinstance(raw, (dt.datetime, pd.Timestamp)):
        # célula com formato de data: o Excel guarda o número digitado como "dias desde 30/12/1899",
        # então esse número de dias é a própria NF (ex.: 850725 aparece como 15/03/4229).
        # Data perto do ano da planilha é data digitada de verdade na coluna da NF: aí não há o que recuperar.
        ts = pd.Timestamp(raw)
        if abs(ts.year - ANO) <= 1:
            r["nf"] = "A CORRIGIR"
            pend.append((r["planilha"], r["linha"], "nf_virou_data", f"Digitaram uma data na coluna da NF ({ts:%d/%m/%Y})"))
        else:
            serial = (ts.normalize() - pd.Timestamp(1899, 12, 30)).days
            r["nf"] = str(serial)
            r["obs"] = ((r["obs"] + " | ") if r["obs"] else "") + f"NF recuperada: Excel mostrava como data {ts:%d/%m/%Y}"
            pend.append((r["planilha"], r["linha"], "nf_virou_data", f"Excel mostrava a NF como data ({ts:%d/%m/%Y}); usada NF {serial} — conferir"))
    elif isinstance(raw, float) and raw.is_integer():
        r["nf"] = str(int(raw))
    else:
        r["nf"] = re.sub(r"\s+", " ", str(raw)).strip()[:60]
    nk = norm(r["nf"])
    if nk.startswith("CREDITO"):
        r["classe"] = "credito"
    elif nk.startswith("RETIRADA") or nk.startswith("RET."):
        r["classe"] = "retirada"
    elif nk.startswith("EMPRESTIMO"):
        r["classe"] = "emprestimo"
    elif nk.startswith("CUSTA BOLETO") or nk.startswith("CUSTO BOLETO") or nk == "JUROS":
        r["classe"] = "despesa"
    elif re.match(r"^(DESCONTO|DES-\d+)", nk):
        r["classe"] = "desconto"
    else:
        r["classe"] = "frete"

# ------------------------------------------------------------------ movimentos de crédito por planilha
movs = []          # dict(planilha, ordem, vendedor, data, tipo, valor, obs, par)
abertura = defaultdict(dict)   # ordem -> {vendedor: valor}
data_abertura = {}
for ordem in sorted({r["ordem"] for r in linhas}):
    da_planilha = [r for r in linhas if r["ordem"] == ordem and r["classe"] in ("credito", "retirada", "emprestimo", "despesa")]
    for r in da_planilha:
        r["valor"] = round((r["vc"] or 0) - (r["vt"] or 0), 2)
        if not r["vendedor"]:
            pend.append((r["planilha"], r["linha"], "movimento_sem_vendedor", f"{r['nf']} {r['valor']}"))
            r["usado"] = True
        elif r["valor"] == 0:
            r["usado"] = True
    # transferências: RETIRADA casa com CREDITO de mesmo valor (oposto) e mesma data, de outro vendedor
    for ret in [r for r in da_planilha if r["classe"] == "retirada" and not r.get("usado")]:
        par = next((c for c in da_planilha if c["classe"] == "credito" and not c.get("usado")
                    and c["data"] == ret["data"] and c["valor"] == -ret["valor"] and c["vendedor"] != ret["vendedor"]), None)
        if par:
            ret["usado"] = par["usado"] = True
            chave = f"T{len(movs)}"
            movs.append(dict(r=ret, tipo="transferencia", valor=ret["valor"], par=chave + "b", chave=chave + "a",
                             obs=f"Transferência para {par['vendedor']}" + (f" | {ret['obs']}" if ret["obs"] else "")))
            movs.append(dict(r=par, tipo="transferencia", valor=par["valor"], par=chave + "a", chave=chave + "b",
                             obs=f"Transferência de {ret['vendedor']}" + (f" | {par['obs']}" if par["obs"] else "")))
    # saldo trazido = "CREDITO" puro nas datas que concentram essas linhas (a observação costuma ser só quem lançou);
    # "CREDITO" em data avulsa ou com texto na NF (ex.: "CREDITO ESTORNO") é crédito extra do mês
    puros = [r for r in da_planilha if not r.get("usado") and r["classe"] == "credito" and norm(r["nf"]) == "CREDITO"]
    por_data = pd.Series([r["data"] for r in puros]).value_counts() if puros else pd.Series(dtype=int)
    datas_saldo = {d for d, n in por_data.items() if n >= max(3, 0.2 * len(puros))}
    for r in da_planilha:
        if r.get("usado"):
            continue
        if r["classe"] == "credito" and norm(r["nf"]) == "CREDITO" and r["data"] in datas_saldo:
            abertura[ordem][r["vendedor"]] = round(abertura[ordem].get(r["vendedor"], 0) + r["valor"], 2)
            data_abertura.setdefault(ordem, []).append(r["data"])
            continue
        tipo, desc = {"credito": ("ajuste", "Crédito"), "retirada": ("ajuste", "Retirada"),
                      "emprestimo": ("ajuste", "Empréstimo"), "despesa": ("despesa", "Despesa")}[r["classe"]]
        extra = " ".join(x for x in [r["nf"] if norm(r["nf"]) not in ("CREDITO", "RETIRADA") else None, r["obs"]] if x)
        movs.append(dict(r=r, tipo=tipo, valor=r["valor"], obs=f"{desc} (planilha {r['rotulo']})" + (f": {extra}" if extra else "")))

# ------------------------------------------------------------------ fretes (sem repetir entre planilhas)
fretes = []
vistos = {}
for r in sorted([r for r in linhas if r["classe"] == "frete"], key=lambda x: (x["ordem"], x["linha"])):
    if r["vc"] is None and r["vt"] is None:
        pend.append((r["planilha"], r["linha"], "linha_sem_valor_ignorada", f"NF {r['nf']}"))
        continue
    tr = r["transp"] or ("CORREIO" if norm(r["nf"]).startswith("CORREIO") else None)
    if not tr:
        pend.append((r["planilha"], r["linha"], "frete_sem_transportadora", f"NF {r['nf']}: usada '{SEM_TRANSP}'"))
        tr = SEM_TRANSP
    r["transp"] = tr
    chave = (norm(r["nf"]), r["data"], r["vc"], tr)
    if chave in vistos and vistos[chave]["planilha"] != r["planilha"]:   # mesma linha em duas planilhas
        f = vistos[chave]
        if f["vt"] is None and r["vt"] is not None:   # a planilha mais nova completou o valor da transportadora
            f["vt"] = r["vt"]
        f["doc"] = f["doc"] or r["doc"]
        pend.append((r["planilha"], r["linha"], "frete_repetido_ignorado", f"NF {r['nf']} já está em {f['planilha']}"))
        continue
    if r["uf"] and r["uf"] not in UFS:
        r["obs"] = ((r["obs"] + " | ") if r["obs"] else "") + f"UF original: {r['uf']}"
        r["uf"] = None
    if not r["vendedor"]:
        pend.append((r["planilha"], r["linha"], "frete_sem_vendedor", f"NF {r['nf']} (fica fora do saldo)"))
    if chave in vistos:   # repetida dentro da mesma planilha: a planilha soma as duas, então entram as duas
        pend.append((r["planilha"], r["linha"], "frete_repetido_na_mesma_planilha", f"NF {r['nf']} (linha {vistos[chave]['linha']}) — conferir"))
    vistos.setdefault(chave, r)
    fretes.append(r)

# ------------------------------------------------------------------ faturas
faturas = {}


def fatura(tr, nd):
    k = (tr, nd)
    if k not in faturas:
        faturas[k] = dict(id=len(faturas) + 1, transp=tr, doc=nd, emp=None, data=None, venc=None, envio=None, resp=None,
                          rec_por=None, rec_em=None, status="aberta", obs=[], desconto=0.0)
    return faturas[k]


for r in fretes:
    if r["doc"] and r["transp"] != SEM_TRANSP:
        f = fatura(r["transp"], r["doc"])
        f["emp"] = f["emp"] or r["empresa"]
        f["data_fretes"] = min(f.get("data_fretes") or r["data"], r["data"])
doc_transp = {}
for (tr, nd) in faturas:
    doc_transp.setdefault(nd, tr)
for b in sorted(boletos, key=lambda x: (x["ordem"], x["linha"])):     # planilha mais nova sobrescreve o status
    tr = b["transp"] or doc_transp.get(b["doc"])
    if not tr:
        pend.append((b["planilha"], b["linha"], "fatura_sem_transportadora", f"Validação: fatura {b['doc']}"))
        continue
    f = fatura(tr, b["doc"])
    f["emp"] = f["emp"] or b["empresa"]
    if f["data"] is None and pd.notna(b["data"]):
        f["data"] = b["data"].date()
    if pd.notna(b["venc"]):
        f["venc"] = b["venc"].date()
    if pd.notna(b["envio"]):
        f["envio"] = b["envio"].date()
    f["resp"] = b["resp"] or f["resp"]
    # RCTO = confirmação de pagamento (no sistema: "Marcar como paga" > Recebido em / por).
    # Na planilha vem como data, "NOME dd/mm", "FATURA PAGA dd/mm" ou "PAGO..."; outro texto vira observação.
    rc = b["rcto"]
    if isinstance(rc, (dt.datetime, pd.Timestamp)) and pd.notna(rc):
        f["paga"], f["rec_em"] = True, pd.Timestamp(rc).date()
    elif texto(rc):
        t = norm(rc)
        md = re.search(r"(\d{1,2})/(\d{1,2})", t)
        quem = re.sub(r"\b(FATURA|PAGA|PAGO|SO|ARQUIVAR)\b", "", t.split(md.group(0))[0] if md else "").strip(" .-")
        if md:
            try:
                f["rec_em"] = dt.date(ANO, int(md.group(2)), int(md.group(1)))
                f["paga"] = True
                f["rec_por"] = quem or f["rec_por"]
            except ValueError:
                f["obs"].append(f"RCTO: {texto(rc)}")
        elif "PAG" in t:
            f["paga"] = True
        else:
            f["obs"].append(f"RCTO: {texto(rc)}")
    if b["obs"]:
        md = re.search(r"DES\s*-?\s*([\d.]+,\d{2})", b["obs"].upper())
        if md:
            f["desconto"] = float(md.group(1).replace(".", "").replace(",", "."))
        if b["obs"] not in f["obs"]:
            f["obs"].append(b["obs"])
    if f.get("paga"):
        f["status"] = "paga"
    elif "DIVERG" in " ".join(f["obs"]).upper() or any(o.startswith("RCTO:") and "??" in o for o in f["obs"]):
        f["status"] = "divergente"
    else:
        f["status"] = "enviada_pagto" if f["envio"] else "aberta"
for r in [r for r in linhas if r["classe"] == "desconto"]:
    k = (r["transp"], r["doc"])
    if k in faturas:
        md = re.search(r"DES\s*-?\s*([\d.]+,\d{2})", (r["obs"] or "").upper())
        d = float(md.group(1).replace(".", "").replace(",", ".")) if md else abs(r["vc"] or 0)
        if d:
            faturas[k]["desconto"] = d
    else:
        pend.append((r["planilha"], r["linha"], "desconto_sem_fatura", f"{r['nf']} doc {r['doc']} {r['transp']}"))
for f in faturas.values():
    if f["data"] is None:
        f["data"] = f.get("data_fretes") or dt.date(ANO, 1, 1)
for r in fretes:
    k = (r["transp"], r["doc"])
    r["fatura_id"] = faturas[k]["id"] if r["doc"] and k in faturas else None
    if not r["doc"]:
        pend.append((r["planilha"], r["linha"], "frete_sem_documento", f"NF {r['nf']}"))

# ------------------------------------------------------------------ saldo ancorado em cada planilha
saldo = defaultdict(float)
ordens = sorted({r["ordem"] for r in linhas})
rotulo_de = {r["ordem"]: r["rotulo"] for r in linhas}
mov_final = []      # movimentos na ordem em que entram no banco
primeira = True
saldo_fim = {}
for ordem in ordens:
    if abertura.get(ordem):
        d_ab = pd.Series(data_abertura[ordem]).mode()[0]
        if primeira:
            for v, val in abertura[ordem].items():
                if val:
                    mov_final.append(dict(vendedor=v, data=d_ab, tipo="saldo_inicial", valor=val,
                                          obs=f"Saldo inicial trazido da planilha de {rotulo_de[ordem]}"))
                saldo[v] = val
            primeira = False
        else:
            for v in sorted(set(abertura[ordem]) | {v for v, s in saldo.items() if abs(s) >= 0.01}):
                alvo = abertura[ordem].get(v, 0.0)
                aj = round(alvo - saldo[v], 2)
                if abs(aj) >= 0.01:
                    mov_final.append(dict(vendedor=v, data=d_ab, tipo="ajuste", valor=aj,
                                          obs=f"Ajuste para o saldo inicial da planilha de {rotulo_de[ordem]}"))
                    ajustes_rel.append(dict(planilha=rotulo_de[ordem], vendedor=v, saldo_calculado=round(saldo[v], 2),
                                            saldo_planilha=alvo, ajuste=aj,
                                            observacao="vendedor não está na lista de CREDITO" if v not in abertura[ordem] else ""))
                saldo[v] = alvo
    elif primeira:
        pend.append(("", "", "planilha_sem_saldo_inicial", rotulo_de[ordem]))
    for r in fretes:
        if r["ordem"] == ordem and r["vendedor"] and r["vt"] is not None:
            saldo[r["vendedor"]] += (r["vc"] or 0) - r["vt"]
    for m in movs:
        if m["r"]["ordem"] == ordem:
            mov_final.append(dict(vendedor=m["r"]["vendedor"], data=m["r"]["data"], tipo=m["tipo"], valor=m["valor"],
                                  obs=m["obs"][:255], chave=m.get("chave"), par=m.get("par")))
            saldo[m["r"]["vendedor"]] += m["valor"]
    saldo_fim[ordem] = dict(saldo)

ids_mov = {}
for i, m in enumerate(mov_final, start=1):
    m["id"] = i
    if m.get("chave"):
        ids_mov[m["chave"]] = i

# ------------------------------------------------------------------ cadastros
ultima = max(ordens)
recentes = {o for o in ordens if o >= ultima - 1}
gestor_de = {}
for r in sorted(linhas, key=lambda x: (x["ordem"], x["linha"])):
    if r["vendedor"] and r["gestor"]:
        gestor_de[r["vendedor"]] = r["gestor"]
vendedores = {r["vendedor"] for r in linhas if r["vendedor"]}
sem_gestor = sorted(v for v in vendedores if v not in gestor_de)
for v in sem_gestor:
    pend.append(("", "", "vendedor_sem_gestor", f"{v}: entra ligado ao gestor '{SEM_TRANSP}'"))
    gestor_de[v] = SEM_TRANSP
ativos_v = {r["vendedor"] for r in linhas if r["ordem"] in recentes and r["vendedor"]}
gestores = set(gestor_de.values()) | {r["gestor"] for r in linhas if r["gestor"]}
ativos_g = {gestor_de[v] for v in ativos_v} | {r["gestor"] for r in linhas if r["ordem"] in recentes and r["gestor"]}
empresas = {r["empresa"] for r in fretes if r["empresa"]} | {f["emp"] for f in faturas.values() if f["emp"]}
transps = {r["transp"] for r in fretes} | {f["transp"] for f in faturas.values()}

# ------------------------------------------------------------------ SQL
out = []
w = out.append
w(f"-- Gerado por tools/importar_planilhas.py em {dt.datetime.now():%d/%m/%Y %H:%M}")
w(f"-- Planilhas: {', '.join(p.name for p in planilhas)}")
w(f"SET NAMES utf8mb4;\nUSE `{BANCO}`;\nSET FOREIGN_KEY_CHECKS = 0;\nSTART TRANSACTION;\n")
w("-- cadastros: só acrescenta nomes que ainda não existem (não altera os existentes)")
w("INSERT IGNORE INTO empresas (nome) VALUES\n  " + ",\n  ".join(f"({q(n)})" for n in sorted(empresas)) + ";")
w("INSERT IGNORE INTO transportadoras (nome) VALUES\n  " + ",\n  ".join(f"({q(n)})" for n in sorted(transps)) + ";")
w("INSERT IGNORE INTO gestores (nome, ativo) VALUES\n  " +
  ",\n  ".join(f"({q(n)}, {1 if n in ativos_g else 0})" for n in sorted(gestores)) + ";")
for v in sorted(vendedores):
    w(f"INSERT IGNORE INTO vendedores (nome, gestor_id, ativo) SELECT {q(v)}, id, {1 if v in ativos_v else 0} "
      f"FROM gestores WHERE nome = {q(gestor_de[v])};")


def sub(tabela, valor):
    return "NULL" if not valor else f"(SELECT id FROM {tabela} WHERE nome = {q(valor)})"


w("\n-- refaz fretes, faturas e movimentos de crédito a partir das planilhas")
w("DELETE FROM fretes;\nDELETE FROM movimentos_credito;\nDELETE FROM faturas;")


def lotes(itens, n=400):
    for i in range(0, len(itens), n):
        yield itens[i:i + n]


fs = sorted(faturas.values(), key=lambda f: f["id"])
for lote in lotes(fs):
    w("INSERT INTO faturas (id, transportadora_id, empresa_id, numero_documento, data_entrada, vencimento, desconto,"
      " status, responsavel, enviado_pagto_em, recebido_por, recebido_em, obs) VALUES\n" + ",\n".join(
        f"  ({f['id']}, {sub('transportadoras', f['transp'])}, {sub('empresas', f['emp'])}, {q(f['doc'])}, {q(f['data'])}, "
        f"{q(f['venc'])}, {f['desconto']}, {q(f['status'])}, {q(f['resp'])}, {q(f['envio'])}, {q(f['rec_por'])}, "
        f"{q(f['rec_em'])}, {q(' | '.join(f['obs'])[:255] or None)})" for f in lote) + ";")
for lote in lotes(fretes):
    w("INSERT INTO fretes (data, nota_fiscal, empresa_id, transportadora_id, vendedor_id, gestor_id, uf, valor_cliente,"
      " valor_transp, fatura_id, obs) VALUES\n" + ",\n".join(
        f"  ({q(r['data'])}, {q(r['nf'])}, {sub('empresas', r['empresa'])}, {sub('transportadoras', r['transp'])}, "
        f"{sub('vendedores', r['vendedor'])}, {sub('gestores', r['gestor'] or gestor_de.get(r['vendedor']))}, {q(r['uf'])}, "
        f"{r['vc'] or 0.0}, {q(r['vt'])}, {q(r['fatura_id'])}, {q((r['obs'] or '')[:255] or None)})" for r in lote) + ";")
for lote in lotes(mov_final):
    w("INSERT INTO movimentos_credito (id, vendedor_id, data_ref, tipo, valor, transferencia_par_id, obs) VALUES\n" + ",\n".join(
        f"  ({m['id']}, {sub('vendedores', m['vendedor'])}, {q(m['data'])}, {q(m['tipo'])}, {m['valor']}, "
        f"{q(ids_mov.get(m.get('par')))}, {q(m['obs'])})" for m in lote) + ";")
w("\nCOMMIT;\nSET FOREIGN_KEY_CHECKS = 1;")
w("ALTER TABLE fretes AUTO_INCREMENT = 1;\nALTER TABLE faturas AUTO_INCREMENT = 1;\nALTER TABLE movimentos_credito AUTO_INCREMENT = 1;")
w("\n-- conferência")
w("SELECT 'fretes' item, COUNT(*) qtd FROM fretes UNION ALL SELECT 'faturas', COUNT(*) FROM faturas "
  "UNION ALL SELECT 'movimentos', COUNT(*) FROM movimentos_credito "
  "UNION ALL SELECT 'fretes sem transportadora', COUNT(*) FROM fretes WHERE transportadora_id IS NULL "
  "UNION ALL SELECT 'movimentos sem vendedor', COUNT(*) FROM movimentos_credito WHERE vendedor_id IS NULL "
  "UNION ALL SELECT 'faturas sem transportadora', COUNT(*) FROM faturas WHERE transportadora_id IS NULL;")
(SAIDA / "importacao.sql").write_text("\n".join(out), encoding="utf-8")

# ------------------------------------------------------------------ saldos esperados e conferência com a aba Saldo
final = saldo_fim[ultima]
pd.DataFrame(sorted(final.items()), columns=["vendedor", "saldo"]).round(2).to_csv(
    SAIDA / "saldos_esperados.csv", index=False, sep=";", encoding="utf-8")


def ler_aba_saldo(arq):
    """A aba tem blocos por gestor (GESTOR / VENDEDORES / ... / TOTAL / STATUS); lê só as linhas de vendedores."""
    s = pd.read_excel(arq, "Saldo", header=None)
    res = {}
    for lin in range(len(s)):
        for j in range(s.shape[1] - 1):
            if norm(s.iat[lin, j]) != "VENDEDORES":
                continue
            for i in range(lin + 1, len(s)):
                n = nome(s.iat[i, j])
                if n in ("TOTAL", "STATUS", "GESTOR", "VENDEDORES"):
                    break
                v = num(s.iat[i, j + 1])
                if n and v is not None:
                    res[n] = round(res.get(n, 0) + v, 2)
    return res


conf = []
ord_conf = max(o for o in ordens if abertura.get(o))     # última planilha com saldo inicial (outubro)
arq_conf = next(p for i, p in enumerate(planilhas) if i == ord_conf)
try:
    aba = ler_aba_saldo(arq_conf)
except Exception as e:  # noqa: BLE001
    aba = {}
    pend.append((arq_conf.name, "", "aba_saldo_ilegivel", str(e)))
sem_vt = defaultdict(float)
for r in fretes:
    if r["ordem"] <= ord_conf and r["vendedor"] and r["vt"] is None:
        sem_vt[r["vendedor"]] += r["vc"] or 0
calc = saldo_fim[ord_conf]
for v in sorted(set(aba) | {v for v, s in calc.items() if abs(s) >= 0.01}):
    c, p = round(calc.get(v, 0), 2), aba.get(v)
    conf.append(dict(vendedor=v, saldo_sistema=c, saldo_aba_saldo=p, diferenca=None if p is None else round(c - p, 2),
                     fretes_sem_valor_transp=round(sem_vt.get(v, 0), 2), saldo_final_com_novembro=round(final.get(v, 0), 2)))

with pd.ExcelWriter(SAIDA / "relatorio_importacao.xlsx") as xw:
    tot = dict(planilha="TOTAL", mes="", linhas_valores=sum(x["linhas_valores"] for x in resumo),
               faturas_validacao=sum(x["faturas_validacao"] for x in resumo))
    rs = pd.DataFrame(resumo + [tot])
    por_pl = defaultdict(lambda: dict(fretes=0, movimentos=0))
    for r in fretes:
        por_pl[r["planilha"]]["fretes"] += 1
    for m in movs:
        por_pl[m["r"]["planilha"]]["movimentos"] += 1
    rs["fretes_importados"] = [por_pl[p]["fretes"] if p != "TOTAL" else len(fretes) for p in rs["planilha"]]
    rs["movimentos_do_mes"] = [por_pl[p]["movimentos"] if p != "TOTAL" else len(movs) for p in rs["planilha"]]
    rs.to_excel(xw, sheet_name="Resumo", index=False)
    pd.DataFrame([dict(item=k, quantidade=v) for k, v in [
        ("fretes", len(fretes)), ("faturas", len(faturas)), ("movimentos de crédito", len(mov_final)),
        ("ajustes de saldo", len(ajustes_rel)), ("datas corrigidas", len(datas_corrigidas)),
        ("vendedores", len(vendedores)), ("gestores", len(gestores)), ("empresas", len(empresas)),
        ("transportadoras", len(transps))]]).to_excel(xw, sheet_name="Totais", index=False)
    pd.DataFrame(ajustes_rel).to_excel(xw, sheet_name="Ajustes de saldo", index=False)
    pd.DataFrame(conf).to_excel(xw, sheet_name="Conferencia saldo", index=False)
    pd.DataFrame(datas_corrigidas).to_excel(xw, sheet_name="Datas corrigidas", index=False)
    pd.DataFrame(pend, columns=["planilha", "linha", "tipo", "detalhe"]).to_excel(xw, sheet_name="Pendencias", index=False)
    pd.DataFrame([dict(vendedor=v, gestor=gestor_de[v], ativo="sim" if v in ativos_v else "não")
                  for v in sorted(vendedores)]).to_excel(xw, sheet_name="Vendedores", index=False)

print(f"fretes {len(fretes)} | faturas {len(faturas)} | movimentos {len(mov_final)} | ajustes {len(ajustes_rel)} | "
      f"datas corrigidas {len(datas_corrigidas)} | pendências {len(pend)}")
print(f"vendedores {len(vendedores)} | gestores {len(gestores)} | empresas {len(empresas)} | transportadoras {len(transps)}")
print("pendências por tipo:", pd.Series([p[2] for p in pend]).value_counts().to_dict())
dif = [c for c in conf if c["diferenca"] not in (None, 0)]
print(f"conferência {rotulo_de[ord_conf]}: {len(conf)} vendedores, {len(dif)} com diferença; "
      f"soma sistema {sum(c['saldo_sistema'] for c in conf):.2f} x aba Saldo {sum(c['saldo_aba_saldo'] or 0 for c in conf):.2f}")
print(f"saldo final total (com novembro): {sum(final.values()):.2f}")
