// @ts-check
/* Formulário de frete: máscara de valores, cobrança/isenção, diferença ao vivo e filtro dos fretes grátis por vendedor. */
(function () {
  const form = /** @type {HTMLFormElement|null} */ (document.getElementById('formFrete'));
  if (!form) return;

  const fmt = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  /** "1.234,56" -> 1234.56 ; "" -> null
   * @param {string} t @returns {number|null} */
  const ler = (t) => {
    t = t.trim().replace(/R\$|\s/g, '');
    if (t === '') return null;
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const n = Number(t);
    return Number.isFinite(n) ? n : NaN;
  };

  const cliente = /** @type {HTMLInputElement} */ (form.querySelector('#valor_cliente'));
  const transp = /** @type {HTMLInputElement} */ (form.querySelector('#valor_transp'));
  const saida = /** @type {HTMLElement} */ (document.getElementById('diferenca'));
  const texto = /** @type {HTMLElement} */ (document.getElementById('diferencaTexto'));
  const caixa = /** @type {HTMLElement} */ (document.getElementById('caixaDiferenca'));

  // cobrança: cobrado do cliente x isento (a empresa assume, não mexe no saldo do vendedor)
  const isento = /** @type {HTMLInputElement} */ (form.querySelector('#cobrancaIsento'));
  const motivo = /** @type {HTMLSelectElement} */ (form.querySelector('#isencao_id'));
  const pedido = /** @type {HTMLInputElement} */ (form.querySelector('#valor_pedido'));
  const ajudaCobranca = /** @type {HTMLElement} */ (document.getElementById('ajudaCobranca'));
  const ajudaPedido = /** @type {HTMLElement} */ (document.getElementById('ajudaPedido'));
  const pedidoObrig = form.querySelectorAll('.pedido-obrig');   // nº e valor do pedido (ERP)
  const minimo = Number(pedido.dataset.minimo || 0);
  const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  function atualizarCobranca() {
    const ehIsento = isento.checked;
    form.querySelectorAll('.bloco-isento').forEach((b) => { /** @type {HTMLElement} */ (b).hidden = !ehIsento; });
    form.querySelectorAll('.bloco-cliente').forEach((b) => { /** @type {HTMLElement} */ (b).hidden = ehIsento; });
    ajudaCobranca.textContent = ehIsento
      ? 'O cliente não paga e o custo fica com a empresa: não entra no saldo do vendedor.'
      : 'A diferença entre o cobrado do cliente e a transportadora vai para o saldo do vendedor.';
    if (ehIsento && solic) solic.value = '';
    atualizarPedido();
    atualizarDiferenca();
  }

  function atualizarPedido() {
    const exige = motivo.selectedOptions[0]?.dataset.exige === '1';
    const v = ler(pedido.value);
    pedidoObrig.forEach((s) => { /** @type {HTMLElement} */ (s).hidden = !exige; });
    ajudaPedido.classList.remove('status-critico-texto');
    if (!exige) { ajudaPedido.textContent = 'Pedido opcional para este motivo.'; return; }
    if (v !== null && !Number.isNaN(v) && v < minimo) {
      ajudaPedido.classList.add('status-critico-texto');
      ajudaPedido.textContent = `Abaixo de ${moeda.format(minimo)}: não se enquadra na política.`;
    } else {
      ajudaPedido.textContent = `A política vale para pedidos a partir de ${moeda.format(minimo)}.`;
    }
  }

  function atualizarDiferenca() {
    const c = isento.checked ? 0 : (ler(cliente.value) ?? 0);
    const t = ler(transp.value);
    caixa.classList.remove('positivo', 'negativo');
    if (isento.checked) {
      saida.textContent = t === null || Number.isNaN(t) ? '—' : 'R$ ' + fmt.format(t);
      texto.textContent = 'Custo da empresa · não altera o saldo';
      return;
    }
    if (Number.isNaN(c) || (t !== null && Number.isNaN(t))) {
      saida.textContent = '—'; texto.textContent = 'Valor inválido'; return;
    }
    if (t === null) {
      saida.textContent = '—'; texto.textContent = 'Aguardando valor da transportadora'; return;
    }
    const d = Math.round((c - t) * 100) / 100;
    saida.textContent = (d < 0 ? '-R$ ' : 'R$ ') + fmt.format(Math.abs(d));
    if (d > 0) { caixa.classList.add('positivo'); texto.textContent = 'Vira crédito para o vendedor'; }
    else if (d < 0) { caixa.classList.add('negativo'); texto.textContent = 'Consome crédito do vendedor'; }
    else { texto.textContent = 'Não altera o saldo'; }
  }

  // ao sair do campo, formata como moeda brasileira
  [cliente, transp, pedido].forEach((campo) => {
    campo.addEventListener('input', atualizarDiferenca);
    campo.addEventListener('blur', () => {
      const n = ler(campo.value);
      if (n !== null && !Number.isNaN(n)) campo.value = fmt.format(n);
    });
  });
  pedido.addEventListener('input', atualizarPedido);
  motivo.addEventListener('change', atualizarPedido);
  form.querySelectorAll('input[name=cobranca]').forEach((r) => r.addEventListener('change', atualizarCobranca));

  // frete grátis: só os do vendedor escolhido
  const vendedor = /** @type {HTMLSelectElement} */ (form.querySelector('#vendedor_id'));
  const solic = /** @type {HTMLSelectElement} */ (form.querySelector('#solicitacao_id'));
  const ajuda = /** @type {HTMLElement} */ (document.getElementById('ajudaSolicitacao'));
  atualizarCobranca();

  function filtrarSolicitacoes() {
    let qtd = 0;
    Array.from(solic.options).forEach((op) => {
      if (!op.value) return;
      const visivel = op.dataset.vendedor === vendedor.value;
      op.hidden = !visivel; op.disabled = !visivel;
      if (visivel) qtd++;
    });
    if (solic.selectedOptions[0]?.disabled) solic.value = '';
    solic.disabled = qtd === 0;
    ajuda.textContent = !vendedor.value ? 'Escolha o vendedor primeiro.'
      : qtd ? `${qtd} frete(s) grátis aprovado(s) para este vendedor.`
      : 'Este vendedor não tem frete grátis aprovado.';
  }
  vendedor.addEventListener('change', filtrarSolicitacoes);
  filtrarSolicitacoes();

  // escolher um frete grátis sugere "cliente = 0"
  solic.addEventListener('change', () => {
    if (solic.value && isento.checked) {      // usar crédito do vendedor = não é isento
      /** @type {HTMLInputElement} */ (form.querySelector('#cobrancaCliente')).checked = true;
      atualizarCobranca();
    }
    if (solic.value && (ler(cliente.value) ?? 0) > 0 && confirmarZerar()) {
      cliente.value = '0,00'; atualizarDiferenca();
    }
  });
  /** sem diálogos do navegador: mostra uma dica em vez de confirm() */
  function confirmarZerar() {
    ajuda.textContent = 'Frete grátis: o valor cobrado do cliente foi zerado. Ajuste se o cliente pagou parte.';
    return true;
  }

  // evita duplo envio
  form.addEventListener('submit', () => {
    setTimeout(() => form.querySelectorAll('button[type=submit]').forEach((b) => {
      /** @type {HTMLButtonElement} */ (b).disabled = true;
    }), 0);
  });
})();
