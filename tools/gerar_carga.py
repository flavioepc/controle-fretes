#!/usr/bin/env python3
"""
Gera database/04_carga_planilha.sql a partir da planilha CONTROLE_FRETE (Excel)
e um relatório de pendências para corrigir depois na plataforma.

Uso:  python3 tools/gerar_carga.py CONTROLE_FRETE_OUTUBRO_2026.xlsx
"""
import re
import sys
import datetime as dt
from pathlib import Path

import pandas as pd

ARQ = sys.argv[1]
RAIZ = Path(__file__).resolve().parent.parent
SAIDA_SQL = RAIZ / "database" / "04_carga_planilha.sql"
SAIDA_PEND = RAIZ / "database" / "pendencias_importacao.csv"
ANO = 2026

UFS = set("AC AL AM AP BA CE DF ES GO MA MG MS MT PA PB PE PI PR RJ RN RO RR RS SC SE SP TO".split())
MOV_NF = {"CREDITO", "RETIRADA", "CUSTA BOLETO"}       # linhas que não são fretes
DESC_NF = re.compile(r"^(DESCONTO|DES-\d+)$")

pend = []  # (linha_excel, tipo, detalhe)


def nome(x):
    if x is None or not isinstance(x, (str, int)) and pd.isna(x):
        return None
    s = re.sub(r"\s+", " ", str(x)).strip().upper()
    return s or None


def doc(x):
    if x is None or pd.isna(x):
        return None
    if isinstance(x, float) and x.is_integer():
        x = int(x)
    s = re.sub(r"\s+", " ", str(x)).strip().rstrip(".;-").strip().upper()
    return s or None


def num(x):
    return None if x is None or pd.isna(x) else round(float(x), 2)


def q(v):
    """Literal SQL."""
    if v is None or v is pd.NaT or (isinstance(v, float) and pd.isna(v)):
        return "NULL"
    if isinstance(v, (int, float)):
        return repr(v)
    if isinstance(v, (dt.date, dt.datetime, pd.Timestamp)):
        return "'" + pd.Timestamp(v).strftime("%Y-%m-%d") + "'"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"


# ---------------------------------------------------------------- leitura
val = pd.read_excel(ARQ, "Valores", header=9, usecols="B:N")
val["linha"] = val.index + 11
val = val[val["Nº Nota Fiscal"].notna()].copy()
dados = pd.read_excel(ARQ, "Dados", header=1, usecols="B:I")
bol = pd.read_excel(ARQ, "Validação", header=8, usecols="B:L")
bol["linha"] = bol.index + 10
bol = bol[bol["Nº Fatura"].notna()].copy()

for c in ["Gestor", "Vendedor", "Nome Fabreck", "Nome Trasnportadora", "UF"]:
    val[c] = pd.Series([nome(x) for x in val[c]], index=val.index, dtype=object)

# ---------------------------------------------------------------- cadastros
empresas = sorted(set(filter(None, map(nome, dados["EMPRESA"]))) |
                  set(val["Nome Fabreck"].dropna()))
transps = sorted(set(filter(None, map(nome, dados["TRANSPORTADORA"]))) |
                 set(val["Nome Trasnportadora"].dropna()) |
                 set(filter(None, map(nome, bol["Nome Transportadora"]))) | {"CORREIO"})

vend_gestor = {}
for g, v in zip(map(nome, dados["GESTORES.1"]), map(nome, dados["VENDEDORES"])):
    if g and v:
        vend_gestor[v] = g
for _, r in val.iterrows():
    v, g = r["Vendedor"], r["Gestor"]
    if v and g:
        if v in vend_gestor and vend_gestor[v] != g:
            pend.append((r["linha"], "vendedor_com_2_gestores", f"{v}: {vend_gestor[v]} / {g}"))
        vend_gestor.setdefault(v, g)
gestores = sorted(set(vend_gestor.values()) | set(filter(None, map(nome, dados["GESTORES"]))))

E = {n: i + 1 for i, n in enumerate(empresas)}
T = {n: i + 1 for i, n in enumerate(transps)}
G = {n: i + 1 for i, n in enumerate(gestores)}
V = {n: i + 1 for i, n in enumerate(sorted(vend_gestor))}

# ---------------------------------------------------------------- faturas
faturas = {}   # (transp, doc) -> dict


def fatura(tr, nd, emp, linha):
    k = (tr, nd)
    if k not in faturas:
        faturas[k] = dict(id=len(faturas) + 1, transp=tr, doc=nd, emp=emp, data=None, venc=None,
                          envio=None, resp=None, rec_por=None, rec_em=None, status="aberta",
                          obs=[], desconto=0.0)
    f = faturas[k]
    f["emp"] = f["emp"] or emp
    return f


# fretes primeiro: criam as faturas com transportadora/empresa
for _, r in val.iterrows():
    nd, tr = doc(r["Nº Documento"]), r["Nome Trasnportadora"]
    nf = nome(r["Nº Nota Fiscal"]) if not isinstance(r["Nº Nota Fiscal"], dt.datetime) else ""
    if nd and tr and nf not in MOV_NF:
        fatura(tr, nd, r["Nome Fabreck"], r["linha"])

doc_para_transp = {}
for (tr, nd) in faturas:
    doc_para_transp.setdefault(nd, tr)

for _, b in bol.sort_values("linha").iterrows():
    nd = doc(b["Nº Fatura"])
    tr = nome(b["Nome Transportadora"]) or doc_para_transp.get(nd)
    if not tr:
        pend.append((b["linha"], "fatura_sem_transportadora", f"Validação: fatura {nd}"))
        continue
    f = fatura(tr, nd, nome(b["Nome Empresa"]), b["linha"])
    dup = f["data"] is not None
    if dup:
        pend.append((b["linha"], "fatura_duplicada_mesclada", f"{tr} {nd}"))
    if f["data"] is None and pd.notna(b["DATA"]):
        f["data"] = b["DATA"]
    if f["venc"] is None and pd.notna(b["Vencimento"]):
        f["venc"] = b["Vencimento"]
    if pd.notna(b["Passei p/ pagto"]):
        f["envio"] = f["envio"] or b["Passei p/ pagto"]
    f["resp"] = f["resp"] or nome(b["Responsavel"])
    rc = nome(b["RCTO"])
    if rc:
        m = re.match(r"^([A-ZÀ-Ú ]+?)\s+(\d{1,2})/(\d{1,2})$", rc)
        if m:
            f["rec_por"] = m.group(1)
            f["rec_em"] = dt.date(ANO, int(m.group(3)), int(m.group(2)))
        else:
            f["status"] = "divergente"
            f["obs"].append(f"RCTO: {rc}")
    o = b["Observação"]
    if pd.notna(o):
        o = str(o).strip()
        md = re.search(r"DES\s*-?\s*([\d.]+,\d{2})", o.upper())
        if md:
            f["desconto"] = float(md.group(1).replace(".", "").replace(",", "."))
        f["obs"].append(o)
    if f["status"] != "divergente":
        f["status"] = "enviada_pagto" if f["envio"] else "aberta"
    if "DIVERG" in " ".join(f["obs"]).upper():
        f["status"] = "divergente"

# ---------------------------------------------------------------- fretes e movimentos
fretes, movs = [], []
pares_retirada = []
for _, r in val.iterrows():
    L = r["linha"]
    nf_raw = r["Nº Nota Fiscal"]
    vc, vt = num(r["VLR COB CLIENTE"]), num(r["VLR COB TRANSP"])
    vend, gest = r["Vendedor"], r["Gestor"]
    obs = None if pd.isna(r["Obs"]) else str(r["Obs"]).strip()
    data = pd.Timestamp(r["DATA"]).date()

    if isinstance(nf_raw, dt.datetime):
        nf = "A CORRIGIR"
        pend.append((L, "nf_virou_data", f"Excel converteu a NF em data ({nf_raw:%d/%m/%Y})"))
    else:
        nf = re.sub(r"\s+", " ", str(nf_raw)).strip()
    NF = nf.upper()

    # --- linhas que são movimentos de crédito, não fretes
    if NF in MOV_NF:
        valor = round((vc or 0) - (vt or 0), 2)
        if not vend:
            pend.append((L, "movimento_sem_vendedor", nf)); continue
        if valor == 0:
            continue
        tipo = {"CREDITO": "saldo_inicial", "RETIRADA": "transferencia",
                "CUSTA BOLETO": "despesa"}[NF]
        movs.append(dict(v=V[vend], data=data, tipo=tipo, valor=valor, linha=L,
                         obs=obs or {"saldo_inicial": "Saldo anterior trazido da planilha",
                                     "transferencia": "Retirada (transferência)",
                                     "despesa": "Custo de boleto"}[tipo]))
        continue

    # --- desconto de fatura
    if DESC_NF.match(NF):
        nd, tr = doc(r["Nº Documento"]), r["Nome Trasnportadora"]
        if (tr, nd) in faturas:
            f = faturas[(tr, nd)]
            md = re.search(r"DES\s*-?\s*([\d.]+,\d{2})", (obs or "").upper())
            d = float(md.group(1).replace(".", "").replace(",", ".")) if md else (vc or 0)
            if d:
                f["desconto"] = d
        continue

    # --- frete normal
    if vc is None and vt is None:
        pend.append((L, "linha_vazia_ignorada", f"NF {nf}")); continue

    tr = r["Nome Trasnportadora"]
    if not tr and NF.startswith("CORREIO"):
        tr = "CORREIO"
    emp = r["Nome Fabreck"]
    uf = r["UF"]
    if uf and uf not in UFS:
        pend.append((L, "uf_invalida", f"NF {nf}: UF '{uf}'"))
        obs = (obs + " | " if obs else "") + f"UF original: {uf}"
        uf = None
    if not vend:
        pend.append((L, "frete_sem_vendedor", f"NF {nf} ({emp or '-'})"))
    if not emp:
        pend.append((L, "frete_sem_empresa", f"NF {nf}"))
    if vt is None:
        pend.append((L, "frete_sem_valor_transportadora", f"NF {nf}"))
    if data.year != ANO or data < dt.date(ANO, 9, 1):
        pend.append((L, "data_suspeita", f"NF {nf}: {data:%d/%m/%Y}"))
    if vc is not None and round(float(r["VLR COB CLIENTE"]), 2) != float(r["VLR COB CLIENTE"]):
        pend.append((L, "valor_mais_de_2_casas", f"NF {nf}: {r['VLR COB CLIENTE']}"))
    nd = doc(r["Nº Documento"])
    fid = faturas[(tr, nd)]["id"] if nd and tr and (tr, nd) in faturas else None
    if not nd:
        pend.append((L, "frete_sem_documento", f"NF {nf}"))

    fretes.append(dict(data=data, nf=nf, e=E.get(emp), t=T.get(tr), v=V.get(vend),
                       # gestor da própria linha = histórico; senão o gestor atual do vendedor
                       g=G.get(gest) or (G.get(vend_gestor.get(vend)) if vend else None),
                       uf=uf, vc=vc or 0.0, vt=vt, fat=fid, obs=obs, linha=L))

# NFs repetidas
cont = pd.Series([f["nf"] for f in fretes]).value_counts()
for nf, n in cont[cont > 1].items():
    pend.append(("", "nf_repetida", f"NF {nf} aparece {n}x"))

# transferências: casa cada 'retirada' com o crédito de mesmo valor e data
for m in [m for m in movs if m["tipo"] == "transferencia"]:
    par = next((c for c in movs if c["tipo"] == "saldo_inicial" and c["data"] == m["data"]
                and c["valor"] == -m["valor"] and not c.get("usado")), None)
    if par:
        par["usado"] = True
        par["tipo"] = "transferencia"
        par["obs"] = "Transferência recebida"
        m["obs"] = "Transferência enviada"
        pares_retirada.append((m, par))

# ---------------------------------------------------------------- SQL
out = []
w = out.append
w("-- Gerado por tools/gerar_carga.py em " + dt.datetime.now().strftime("%d/%m/%Y %H:%M"))
w(f"-- Origem: {Path(ARQ).name}")
w("SET NAMES utf8mb4;\nUSE fabreck_fretes;\nSET FOREIGN_KEY_CHECKS = 0;\nSTART TRANSACTION;\n")
w("INSERT INTO empresas (id, nome) VALUES\n  " + ",\n  ".join(f"({i}, {q(n)})" for n, i in E.items()) + ";")
w("INSERT INTO transportadoras (id, nome) VALUES\n  " + ",\n  ".join(f"({i}, {q(n)})" for n, i in T.items()) + ";")
w("INSERT INTO gestores (id, nome) VALUES\n  " + ",\n  ".join(f"({i}, {q(n)})" for n, i in G.items()) + ";")
w("INSERT INTO vendedores (id, nome, gestor_id) VALUES\n  " +
  ",\n  ".join(f"({i}, {q(n)}, {G[vend_gestor[n]]})" for n, i in V.items()) + ";")

fs = sorted(faturas.values(), key=lambda f: f["id"])
w("\nINSERT INTO faturas (id, transportadora_id, empresa_id, numero_documento, data_entrada, vencimento,"
  " desconto, status, responsavel, enviado_pagto_em, recebido_por, recebido_em, obs) VALUES")
dmin = min(f["data"] for f in fretes)
rows = []
for f in fs:
    data = f["data"] if f["data"] is not None else min(
        (x["data"] for x in fretes if x["fat"] == f["id"]), default=dmin)
    rows.append(f"  ({f['id']}, {T[f['transp']]}, {q(E.get(f['emp']))}, {q(f['doc'])}, {q(data)}, "
                f"{q(f['venc'])}, {f['desconto']}, {q(f['status'])}, {q(f['resp'])}, {q(f['envio'])}, "
                f"{q(f['rec_por'])}, {q(f['rec_em'])}, {q(' | '.join(f['obs'])[:255] or None)})")
w(",\n".join(rows) + ";")

w("\nINSERT INTO fretes (data, nota_fiscal, empresa_id, transportadora_id, vendedor_id, gestor_id, uf,"
  " valor_cliente, valor_transp, fatura_id, obs) VALUES")
w(",\n".join(f"  ({q(f['data'])}, {q(f['nf'])}, {q(f['e'])}, {q(f['t'])}, {q(f['v'])}, {q(f['g'])}, "
             f"{q(f['uf'])}, {f['vc']}, {q(f['vt'])}, {q(f['fat'])}, {q(f['obs'])})" for f in fretes) + ";")

w("\nINSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, obs) VALUES")
w(",\n".join(f"  ({m['v']}, {q(m['data'])}, {q(m['tipo'])}, {m['valor']}, {q(m['obs'])})" for m in movs) + ";")
for a, b in pares_retirada:
    w(f"UPDATE movimentos_credito a JOIN movimentos_credito b ON a.tipo='transferencia' AND b.tipo='transferencia'"
      f" AND a.vendedor_id={a['v']} AND b.vendedor_id={b['v']} AND a.valor={a['valor']} AND b.valor={b['valor']}"
      f" SET a.transferencia_par_id=b.id, b.transferencia_par_id=a.id;")

w("\nCOMMIT;\nSET FOREIGN_KEY_CHECKS = 1;")
SAIDA_SQL.write_text("\n".join(out), encoding="utf-8")

pend = list(dict.fromkeys(pend if True else []))  # remove duplicadas mantendo ordem
pend = [p for i, p in enumerate(pend)
        if p[1] != "vendedor_com_2_gestores" or p[2] not in [x[2] for x in pend[:i] if x[1] == p[1]]]
pd.DataFrame(pend, columns=["linha_planilha", "tipo", "detalhe"]).to_csv(
    SAIDA_PEND, index=False, sep=";", encoding="utf-8-sig")

print(f"empresas {len(E)} | transportadoras {len(T)} | gestores {len(G)} | vendedores {len(V)}")
print(f"faturas {len(faturas)} | fretes {len(fretes)} | movimentos {len(movs)} | pendências {len(pend)}")
print(pd.Series([p[1] for p in pend]).value_counts().to_string())
