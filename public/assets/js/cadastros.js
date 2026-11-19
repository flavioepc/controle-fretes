// @ts-check
/* Tela de cadastros: um mesmo modal serve para "novo" e "editar", preenchido com os
   data-* do botão clicado (sem cada campo precisar de uma rota só pra buscar os dados). */
(function () {
  /** @type {Record<string, {campos: string[], novo: string, editar: string}>} */
  const modais = {
    modalEmpresa: { campos: ['nome', 'cnpj'], novo: 'Nova empresa', editar: 'Editar empresa' },
    modalTransportadora: { campos: ['nome', 'cnpj'], novo: 'Nova transportadora', editar: 'Editar transportadora' },
    modalGestor: { campos: ['nome', 'usuario_id'], novo: 'Novo gestor', editar: 'Editar gestor' },
    modalVendedor: { campos: ['nome', 'gestor_id', 'usuario_id', 'credito_expira'], novo: 'Novo vendedor', editar: 'Editar vendedor' },
  };

  Object.entries(modais).forEach(([modalId, cfg]) => {
    const modal = document.getElementById(modalId);
    const form = /** @type {HTMLFormElement|null} */ (modal?.querySelector('form'));
    if (!modal || !form) return;
    const titulo = modal.querySelector('.modal-titulo-acao');

    modal.addEventListener('show.bs.modal', (ev) => {
      const botao = /** @type {HTMLElement|undefined} */ (/** @type {any} */ (ev).relatedTarget);
      const editando = !!botao?.dataset.id;
      form.reset();

      const campoId = /** @type {HTMLInputElement|null} */ (form.querySelector('[name="id"]'));
      if (campoId) campoId.value = botao?.dataset.id || '';

      cfg.campos.forEach((campo) => {
        const valor = botao?.dataset[campo] ?? '';
        if (campo === 'credito_expira') {
          const radio = /** @type {HTMLInputElement|null} */ (form.querySelector(`[name="credito_expira"][value="${valor}"]`));
          if (radio) radio.checked = true;
          return;
        }
        const el = /** @type {HTMLInputElement|HTMLSelectElement|null} */ (form.querySelector(`[name="${campo}"]`));
        if (el) el.value = valor;
      });

      if (titulo) titulo.textContent = editando ? cfg.editar : cfg.novo;
    });
  });

  // "Criar acesso": o botão traz o id, o nome e a rota (gestores ou vendedores)
  const modalAcesso = document.getElementById('modalAcesso');
  const formAcesso = /** @type {HTMLFormElement|null} */ (modalAcesso?.querySelector('form'));
  if (modalAcesso && formAcesso) {
    modalAcesso.addEventListener('show.bs.modal', (ev) => {
      const botao = /** @type {HTMLElement|undefined} */ (/** @type {any} */ (ev).relatedTarget);
      if (!botao) return;
      formAcesso.reset();
      formAcesso.action = botao.dataset.acao || '';
      const id = /** @type {HTMLInputElement|null} */ (formAcesso.querySelector('[name="id"]'));
      if (id) id.value = botao.dataset.id || '';
      const nome = modalAcesso.querySelector('.acesso-nome');
      if (nome) nome.textContent = '· ' + (botao.dataset.nome || '');
    });
    modalAcesso.addEventListener('shown.bs.modal', () => {
      /** @type {HTMLInputElement|null} */ (formAcesso.querySelector('[name="email"]'))?.focus();
    });
  }
})();
