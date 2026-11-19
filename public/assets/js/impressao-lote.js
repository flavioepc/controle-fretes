// @ts-check
/* Imprimir faturas em lote: marca/desmarca, mostra o resumo e manda os ids como "ids=1,2,3". */
(function () {
  const form = /** @type {HTMLFormElement|null} */ (document.getElementById('formLote'));
  if (!form) return;
  const todas = /** @type {HTMLInputElement} */ (document.getElementById('marcarTodas'));
  const ids = /** @type {HTMLInputElement} */ (document.getElementById('idsLote'));
  const resumo = /** @type {HTMLElement} */ (document.getElementById('resumoLote'));
  const botao = /** @type {HTMLButtonElement} */ (document.getElementById('btnLote'));
  const caixas = /** @type {HTMLInputElement[]} */ (Array.from(form.querySelectorAll('.sel-fatura')));
  const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  function atualizar() {
    const marcadas = caixas.filter((c) => c.checked);
    const soma = marcadas.reduce((s, c) => s + Number(c.dataset.total || 0), 0);
    resumo.textContent = `${marcadas.length} de ${caixas.length} selecionada(s) · ${fmt.format(soma)}`;
    botao.disabled = marcadas.length === 0;
    todas.checked = marcadas.length === caixas.length;
    todas.indeterminate = marcadas.length > 0 && marcadas.length < caixas.length;
  }

  todas.addEventListener('change', () => { caixas.forEach((c) => { c.checked = todas.checked; }); atualizar(); });
  caixas.forEach((c) => c.addEventListener('change', atualizar));
  form.addEventListener('submit', () => {
    ids.value = caixas.filter((c) => c.checked).map((c) => c.value).join(',');
  });
  atualizar();
})();
