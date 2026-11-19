// @ts-check
/* Pagamentos e transferências: máscara de valor, ajuda com o saldo do vendedor escolhido e modal de estorno. */
(function () {
  const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const num = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  /** "1.234,56" -> 1234.56 ; "" -> null
   * @param {string} t @returns {number|null} */
  const ler = (t) => {
    t = t.trim().replace(/R\$|\s/g, '');
    if (t === '') return null;
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const n = Number(t);
    return Number.isFinite(n) ? n : NaN;
  };

  /**
   * @param {string} idSelect @param {string} idValor @param {string} idAjuda
   * @param {(op: HTMLOptionElement) => number} limite máximo permitido para o vendedor escolhido
   * @param {string} rotulo
   */
  function ligar(idSelect, idValor, idAjuda, limite, rotulo) {
    const sel = /** @type {HTMLSelectElement|null} */ (document.getElementById(idSelect));
    const valor = /** @type {HTMLInputElement|null} */ (document.getElementById(idValor));
    const ajuda = /** @type {HTMLElement|null} */ (document.getElementById(idAjuda));
    if (!sel || !valor || !ajuda) return;

    const atualizar = () => {
      const op = sel.selectedOptions[0];
      ajuda.classList.remove('status-critico-texto');
      if (!op || !op.value) { ajuda.textContent = ' '; return; }
      const max = limite(op);
      const v = ler(valor.value);
      if (v !== null && !Number.isNaN(v) && v > max) {
        ajuda.classList.add('status-critico-texto');
        ajuda.textContent = `Acima do permitido (${fmt.format(max)})`;
      } else {
        ajuda.textContent = `${rotulo}: ${fmt.format(max)}`;
      }
    };
    sel.addEventListener('change', atualizar);
    valor.addEventListener('input', atualizar);
    valor.addEventListener('blur', () => {
      const n = ler(valor.value);
      if (n !== null && !Number.isNaN(n)) valor.value = num.format(n);
    });
    atualizar();
  }

  ligar('pag_vendedor', 'pag_valor', 'pag_ajuda', (op) => -Number(op.dataset.saldo), 'Saldo negativo');
  ligar('tr_origem', 'tr_valor', 'tr_ajuda', (op) => Number(op.dataset.disponivel), 'Disponível para transferir');

  // estorno: preenche o modal com o lançamento clicado
  const modal = document.getElementById('modalEstornar');
  modal?.addEventListener('show.bs.modal', (ev) => {
    const botao = /** @type {HTMLElement} */ (/** @type {any} */ (ev).relatedTarget);
    /** @type {HTMLFormElement} */ (modal.querySelector('form')).reset();
    /** @type {HTMLInputElement} */ (modal.querySelector('.campo-id')).value = botao.dataset.id || '';
    /** @type {HTMLElement} */ (modal.querySelector('.campo-resumo')).textContent = botao.dataset.resumo || '';
    /** @type {HTMLElement} */ (modal.querySelector('.campo-efeito')).textContent = botao.dataset.efeito || '';
  });
  modal?.addEventListener('shown.bs.modal', () => /** @type {HTMLElement} */ (modal.querySelector('textarea'))?.focus());

  // evita duplo envio
  document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', () => {
    setTimeout(() => form.querySelectorAll('button[type=submit]').forEach((b) => {
      /** @type {HTMLButtonElement} */ (b).disabled = true;
    }), 0);
  }));
})();
