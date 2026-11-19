// @ts-check
/* Tela da fatura: marcar fretes para retirar do conhecimento errado (modal #modalRetirar). */
(function () {
  const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const caixas = /** @type {HTMLInputElement[]} */ (Array.from(document.querySelectorAll('.sel-frete')));
  const todas = /** @type {HTMLInputElement|null} */ (document.getElementById('marcarFretes'));
  const botao = /** @type {HTMLButtonElement|null} */ (document.getElementById('btnRetirar'));
  const resumoRodape = document.getElementById('resumoRetirar');
  const resumoModal = document.getElementById('retirarResumo');
  if (!caixas.length || !botao) return;
  const textoInicial = resumoRodape?.textContent || '';

  function atualizar() {
    const sel = caixas.filter((c) => c.checked);
    const total = sel.reduce((s, c) => s + Number(c.dataset.valor || 0), 0);
    /** @type {HTMLButtonElement} */ (botao).disabled = sel.length === 0;
    if (todas) {
      todas.checked = sel.length === caixas.length;
      todas.indeterminate = sel.length > 0 && sel.length < caixas.length;
    }
    if (resumoRodape) {
      resumoRodape.textContent = sel.length
        ? `${sel.length} de ${caixas.length} marcado(s) · ${fmt.format(total)}`
        : textoInicial;
    }
    if (resumoModal) {
      resumoModal.textContent = `${sel.length} frete(s), ${fmt.format(total)}: NF `
        + sel.map((c) => c.dataset.nf).join(', ');
    }
  }

  caixas.forEach((c) => c.addEventListener('change', atualizar));
  todas?.addEventListener('change', () => {
    caixas.forEach((c) => { c.checked = todas.checked; });
    atualizar();
  });
  atualizar();

  // voltou com erro do servidor: reabre o modal com o que foi digitado
  const modal = document.getElementById('modalRetirar');
  if (modal && modal.querySelector('.is-invalid') && /** @type {any} */ (window).bootstrap) {
    /** @type {any} */ (window).bootstrap.Modal.getOrCreateInstance(modal).show();
  }
  modal?.addEventListener('shown.bs.modal', () => /** @type {HTMLElement|null} */ (modal.querySelector('#ret_destino'))?.focus());

  // evita duplo envio
  document.getElementById('formRetirar')?.addEventListener('submit', (ev) => {
    const b = /** @type {HTMLFormElement} */ (ev.currentTarget).querySelector('button[type=submit]');
    setTimeout(() => { if (b) /** @type {HTMLButtonElement} */ (b).disabled = true; }, 0);
  });
})();
