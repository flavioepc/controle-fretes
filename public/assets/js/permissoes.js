// @ts-check
/* Painel de permissões: conta o que foi mudado antes de salvar e marca/desmarca a coluna de um perfil. */
(function () {
  const form = /** @type {HTMLFormElement|null} */ (document.getElementById('formPermissoes'));
  if (!form) return;
  const caixas = /** @type {HTMLInputElement[]} */ (Array.from(form.querySelectorAll('.perm')));
  const resumo = document.getElementById('resumoPermissoes');
  const salvar = /** @type {HTMLButtonElement|null} */ (document.getElementById('btnSalvarPermissoes'));

  function atualizar() {
    const mudadas = caixas.filter((c) => (c.checked ? '1' : '0') !== c.dataset.original);
    caixas.forEach((c) => c.closest('td')?.classList.toggle('table-info', (c.checked ? '1' : '0') !== c.dataset.original));
    if (resumo) resumo.textContent = mudadas.length ? `${mudadas.length} alteração(ões) ainda não salva(s)` : 'Nenhuma alteração.';
    if (salvar) salvar.disabled = mudadas.length === 0;
  }

  caixas.forEach((c) => c.addEventListener('change', atualizar));
  form.querySelectorAll('.marcar-coluna').forEach((b) => b.addEventListener('click', () => {
    const doPerfil = caixas.filter((c) => c.dataset.perfil === /** @type {HTMLElement} */ (b).dataset.perfil);
    const marcar = doPerfil.some((c) => !c.checked);
    doPerfil.forEach((c) => { c.checked = marcar; });
    atualizar();
  }));
  form.addEventListener('reset', () => setTimeout(atualizar, 0));
  window.addEventListener('beforeunload', (ev) => {
    if (salvar && !salvar.disabled && !form.dataset.enviando) ev.preventDefault();
  });
  form.addEventListener('submit', () => { form.dataset.enviando = '1'; });
  atualizar();
})();
