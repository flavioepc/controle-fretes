// @ts-check
/* Tela de uso de crédito: modais de recusar/cancelar e ajuda de saldo no formulário. */
(function () {
  const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  // preenche os modais com o pedido clicado
  ['modalRecusar', 'modalCancelar'].forEach((id) => {
    const modal = document.getElementById(id);
    modal?.addEventListener('show.bs.modal', (ev) => {
      const botao = /** @type {HTMLElement} */ (/** @type {any} */ (ev).relatedTarget);
      /** @type {HTMLInputElement} */ (modal.querySelector('.campo-id')).value = botao.dataset.id || '';
      /** @type {HTMLElement} */ (modal.querySelector('.campo-resumo')).textContent = botao.dataset.resumo || '';
    });
    modal?.addEventListener('shown.bs.modal', () => /** @type {HTMLElement} */ (modal.querySelector('textarea'))?.focus());
  });

  const form = /** @type {HTMLFormElement|null} */ (document.getElementById('formCredito'));
  if (!form) return;

  /** @param {string} t */
  const ler = (t) => {
    t = t.trim().replace(/R\$|\s/g, '');
    if (t === '') return null;
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const n = Number(t);
    return Number.isFinite(n) ? n : NaN;
  };

  const vendedor = /** @type {HTMLSelectElement|HTMLInputElement} */ (form.querySelector('#vendedor_id'));
  const valor = /** @type {HTMLInputElement} */ (form.querySelector('#valor'));
  const ajudaValor = /** @type {HTMLElement} */ (document.getElementById('ajudaValor'));
  const ajudaTipo = /** @type {HTMLElement} */ (document.getElementById('ajudaTipo'));
  const clienteObrig = /** @type {HTMLElement} */ (document.getElementById('clienteObrig'));

  const disponivel = () => {
    const el = vendedor instanceof HTMLSelectElement ? vendedor.selectedOptions[0] : vendedor;
    const d = el?.dataset.disponivel;
    return d === undefined || d === '' ? null : Number(d);
  };

  function atualizar() {
    const disp = disponivel();
    const v = ler(valor.value);
    ajudaValor.classList.remove('status-critico-texto');
    if (disp === null) { ajudaValor.textContent = 'Escolha o vendedor.'; return; }
    if (v !== null && !Number.isNaN(v) && v > disp) {
      ajudaValor.classList.add('status-critico-texto');
      ajudaValor.textContent = `Acima do disponível (${fmt.format(disp)})`;
    } else {
      ajudaValor.textContent = `Disponível: ${fmt.format(disp)}`;
    }
  }

  function tipo() {
    const outro = /** @type {HTMLInputElement} */ (form.querySelector('#tipoOutro')).checked;
    ajudaTipo.textContent = outro
      ? 'Outro uso: ao aprovar, o valor é descontado do saldo na hora (ex.: cobrir falta de caixa).'
      : 'Frete grátis: o valor fica reservado e é baixado quando o frete for lançado.';
    clienteObrig.hidden = outro;
  }

  vendedor.addEventListener('change', atualizar);
  valor.addEventListener('input', atualizar);
  valor.addEventListener('blur', () => {
    const n = ler(valor.value);
    if (n !== null && !Number.isNaN(n)) valor.value = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(n);
  });
  form.querySelectorAll('input[name=tipo]').forEach((r) => r.addEventListener('change', tipo));
  atualizar();
  tipo();

  // abre o formulário se a URL tiver #novo (ex.: voltando com erro)
  if (location.hash === '#novo') document.getElementById('novo')?.classList.add('show');
})();
