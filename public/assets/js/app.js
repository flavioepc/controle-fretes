// @ts-check
/* Scripts comuns a todas as páginas */

/** Alterna e lembra o tema claro/escuro. */
(function () {
  const botao = document.getElementById('alternarTema');
  if (!botao) return;

  /** @param {string} tema */
  const aplicar = (tema) => {
    document.documentElement.setAttribute('data-bs-theme', tema);
    botao.innerHTML = tema === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
    document.dispatchEvent(new CustomEvent('tema-alterado', { detail: tema }));
  };

  aplicar(document.documentElement.getAttribute('data-bs-theme') || 'light');

  botao.addEventListener('click', () => {
    const novo = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('tema', novo); } catch (e) { /* navegador sem armazenamento */ }
    aplicar(novo);
  });
})();

/** Ao corrigir um campo que veio com erro do servidor, tira o destaque vermelho dele. */
(function () {
  /** @param {Event} ev */
  const limpar = (ev) => {
    const el = /** @type {HTMLElement} */ (ev.target);
    if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) return;
    if (!el.classList.contains('is-invalid')) return;
    el.classList.remove('is-invalid');
    el.closest('.ts-wrapper')?.classList.remove('is-invalid');
    const grupo = el.closest('.input-group') || el.parentElement;
    grupo?.querySelectorAll('.invalid-feedback').forEach((f) => { /** @type {HTMLElement} */ (f).textContent = ''; });
  };
  document.addEventListener('input', limpar, true);
  document.addEventListener('change', limpar, true);
})();
