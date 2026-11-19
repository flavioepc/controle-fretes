// @ts-check
/* Transforma <select class="busca-select"> em campo com busca (Tom Select). */
(function () {
  // @ts-ignore - TomSelect vem de assets/vendor
  const TS = window.TomSelect;
  if (!TS) return;

  document.querySelectorAll('select.busca-select').forEach((el) => {
    const select = /** @type {HTMLSelectElement} */ (el);
    const ts = new TS(select, {
      allowEmptyOption: true,
      placeholder: select.dataset.placeholder || 'Digite para buscar…',
      maxOptions: 500,
      searchField: ['text'],
      render: { no_results: () => '<div class="no-results">Nada encontrado</div>' },
      // o Tom Select já dispara 'change' no <select> original
    });
    // mantém o destaque de erro do Bootstrap
    if (select.classList.contains('is-invalid')) ts.wrapper.classList.add('is-invalid');
    if (select.classList.contains('form-select-sm')) ts.wrapper.classList.add('form-select-sm');
  });
})();
