// @ts-check
/* Impressão de faturas: em cada fatura (section.bloco-fatura), o valor do boleto e o vencimento digitados
   acima da folha entram no relatório e mostram se a soma dos fretes bate com o boleto.
   Não grava nada (a conferência oficial é do financeiro, na tela de Faturas). */
(function () {
  const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  /** "1.234,56" -> 1234.56 ; "" -> null
   * @param {string} t @returns {number|null} */
  const ler = (t) => {
    t = t.trim().replace(/R\$|\s/g, '');
    if (t === '') return null;
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const n = Number(t);
    return Number.isFinite(n) ? n : null;
  };

  document.querySelectorAll('.bloco-fatura').forEach((el) => {
    const bloco = /** @type {HTMLElement} */ (el);
    const boleto = /** @type {HTMLInputElement|null} */ (bloco.querySelector('.in-boleto'));
    const venc = /** @type {HTMLInputElement|null} */ (bloco.querySelector('.in-venc'));
    if (!boleto || !venc) return;
    const total = Number(bloco.dataset.total || 0);

    /** @param {string} sel @param {string} html */
    const preencher = (sel, html) => bloco.querySelectorAll(sel).forEach((x) => { x.innerHTML = html; });

    function atualizar() {
      const v = ler(/** @type {HTMLInputElement} */ (boleto).value);
      if (v === null) {
        preencher('[data-boleto]', '—');
        preencher('[data-dif]', '—');
        preencher('[data-selo]', '<span class="selo selo-pendente">INFORME O BOLETO</span>');
      } else {
        const dif = Math.round((v - total) * 100) / 100;
        preencher('[data-boleto]', fmt.format(v));
        preencher('[data-dif]', fmt.format(dif));
        preencher('[data-selo]', Math.abs(dif) <= 0.01
          ? '<span class="selo selo-ok">CONFERE</span>'
          : `<span class="selo selo-erro">DIVERGENTE (${dif > 0 ? 'boleto maior' : 'boleto menor'})</span>`);
      }
      const d = /** @type {HTMLInputElement} */ (venc).value;
      if (d) {
        const [a, m, dia] = d.split('-');
        preencher('[data-venc]', `${dia}/${m}/${a}`);
      }
    }

    boleto.addEventListener('input', atualizar);
    boleto.addEventListener('blur', () => {
      const n = ler(boleto.value);
      if (n !== null) boleto.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
    venc.addEventListener('change', atualizar);
    atualizar();
  });

  document.getElementById('btnImprimir')?.addEventListener('click', () => window.print());

  // histórico: grava que estas faturas foram impressas quando a janela de impressão abre (botão ou Ctrl+P)
  const barra = /** @type {HTMLElement|null} */ (document.querySelector('.barra-impressao'));
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  window.addEventListener('beforeprint', () => {
    if (!barra?.dataset.registrar || !barra.dataset.ids) return;
    const corpo = new FormData();
    corpo.append('ids', barra.dataset.ids);
    corpo.append('modelo', barra.dataset.modelo || 'detalhado');
    fetch(barra.dataset.registrar, { method: 'POST', body: corpo, keepalive: true, headers: { 'X-CSRF-Token': token } })
      .then((r) => {
        if (!r.ok) return;
        document.querySelectorAll('.ja-impressa').forEach((el) => {
          el.classList.add('sim');
          el.textContent = 'Impressa agora';
        });
      })
      .catch(() => { /* sem rede: a impressão segue, só não fica no histórico */ });
  });
})();
