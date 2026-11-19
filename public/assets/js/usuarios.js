// @ts-check
/* Tela "Usuários e acessos": o mesmo modal serve para "novo" e "editar", preenchido com os
   data-* do botão clicado. Aberto pelo servidor após erro de validação (sem botão), mantém
   o que já veio preenchido. */
(function () {
  const modal = document.getElementById('modalUsuario');
  const form = /** @type {HTMLFormElement|null} */ (modal?.querySelector('form'));
  if (!modal || !form) return;
  const titulo = modal.querySelector('.modal-titulo-acao');
  const textoNovo = /** @type {HTMLElement|null} */ (modal.querySelector('.texto-novo'));

  /** @param {string} nome */
  const campo = (nome) => /** @type {HTMLInputElement|null} */ (form.querySelector(`[name="${nome}"]`));

  modal.addEventListener('show.bs.modal', (ev) => {
    const botao = /** @type {HTMLElement|undefined} */ (/** @type {any} */ (ev).relatedTarget);
    if (!botao) {
      // reaberto após erro: os valores enviados já estão no formulário
      const id = campo('id')?.value;
      if (titulo) titulo.textContent = id ? 'Editar usuário' : 'Novo usuário';
      if (textoNovo) textoNovo.hidden = !!id;
      return;
    }

    const editando = !!botao.dataset.id;
    form.reset();
    const id = campo('id'), nome = campo('nome'), email = campo('email'), ativo = campo('ativo');
    if (id) id.value = botao.dataset.id || '';
    if (nome) nome.value = botao.dataset.nome || '';
    if (email) email.value = botao.dataset.email || '';
    if (ativo) ativo.checked = !editando || botao.dataset.ativo === '1';
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

    /** @type {Record<string, string>} */
    let acessos = {};
    try { acessos = JSON.parse(botao.dataset.acessos || '{}') || {}; } catch (e) { acessos = {}; }
    form.querySelectorAll('select[data-sistema]').forEach((el) => {
      const sel = /** @type {HTMLSelectElement} */ (el);
      sel.value = acessos[sel.dataset.sistema || ''] || '';
    });

    if (titulo) titulo.textContent = editando ? 'Editar usuário' : 'Novo usuário';
    if (textoNovo) textoNovo.hidden = editando;
  });
})();
