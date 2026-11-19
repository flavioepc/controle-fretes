// @ts-check
/* global Chart */
/**
 * Gráficos do dashboard (Chart.js 4).
 * Os dados chegam prontos do PHP no <script id="dadosGraficos" type="application/json">.
 */

/**
 * @typedef {{ semanal: boolean, rotulos: string[], cliente: number[], transp: number[] }} DadosSerie
 * @typedef {{ rotulos: string[], valores: number[] }} DadosGrupo
 * @typedef {{ serie: DadosSerie, grupo: DadosGrupo }} DadosGraficos
 */

(function () {
  const elDados = document.getElementById('dadosGraficos');
  // @ts-ignore - Chart vem do CDN
  const ChartJS = window.Chart;
  if (!elDados || !ChartJS) return;

  /** @type {DadosGraficos} */
  const dados = JSON.parse(elDados.textContent || '{}');

  const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const moedaCurta = new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 });

  /** @param {string} iso '2026-10-27' -> '27/10' */
  const diaMes = (iso) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;

  /** Lê as cores do CSS (mudam com o tema). */
  const cores = () => {
    const css = getComputedStyle(document.documentElement);
    /** @param {string} v */
    const v = (nome) => css.getPropertyValue(nome).trim();
    return {
      s1: v('--serie-1'), s2: v('--serie-2'), neg: v('--negativo'),
      grade: v('--grade'), eixo: v('--eixo'), superficie: v('--app-superficie'),
      texto: v('--bs-body-color'),
    };
  };

  /** @type {any[]} */
  let graficos = [];

  function desenhar() {
    graficos.forEach((g) => g.destroy());
    graficos = [];
    const c = cores();

    ChartJS.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    ChartJS.defaults.font.size = 11;
    ChartJS.defaults.color = c.eixo;

    const tooltip = {
      backgroundColor: c.superficie, titleColor: c.texto, bodyColor: c.texto,
      borderColor: c.grade, borderWidth: 1, padding: 10, boxPadding: 4, usePointStyle: true,
    };

    // ---- 1. Cliente × transportadora (linhas, um eixo só) ----
    const elSerie = /** @type {HTMLCanvasElement|null} */ (document.getElementById('graficoSerie'));
    if (elSerie && dados.serie.rotulos.length) {
      const linha = (/** @type {string} */ rotulo, /** @type {number[]} */ valores, /** @type {string} */ cor) => ({
        label: rotulo, data: valores, borderColor: cor, backgroundColor: cor,
        borderWidth: 2, cubicInterpolationMode: 'monotone', pointRadius: dados.serie.rotulos.length > 20 ? 0 : 3,
        pointHoverRadius: 5, pointBorderColor: c.superficie, pointBorderWidth: 2,
      });
      graficos.push(new ChartJS(elSerie, {
        type: 'line',
        data: {
          labels: dados.serie.rotulos.map(diaMes),
          datasets: [
            linha('Cobrado do cliente', dados.serie.cliente, c.s1),
            linha('Cobrado pela transportadora', dados.serie.transp, c.s2),
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },   // passar o mouse mostra os dois valores
          plugins: {
            legend: { display: false },                     // legenda fica no HTML
            tooltip: {
              ...tooltip,
              callbacks: {
                title: (/** @type {any[]} */ itens) =>
                  (dados.serie.semanal ? 'Semana de ' : '') + dados.serie.rotulos[itens[0].dataIndex].split('-').reverse().join('/'),
                label: (/** @type {any} */ i) => ` ${i.dataset.label}: ${moeda.format(i.parsed.y)}`,
                footer: (/** @type {any[]} */ itens) => {
                  const d = dados.serie.cliente[itens[0].dataIndex] - dados.serie.transp[itens[0].dataIndex];
                  return `Diferença: ${moeda.format(d)}`;
                },
              },
            },
          },
          scales: {
            x: { grid: { display: false }, border: { color: c.grade }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
            y: {
              beginAtZero: true, grid: { color: c.grade }, border: { display: false },
              ticks: { callback: (/** @type {number} */ v) => 'R$ ' + moedaCurta.format(v), maxTicksLimit: 6 },
            },
          },
        },
      }));
    }

    // ---- 2. Saldo por gestor / vendedor (barras horizontais, cor pelo sinal) ----
    const elGrupo = /** @type {HTMLCanvasElement|null} */ (document.getElementById('graficoGrupo'));
    if (elGrupo && dados.grupo.rotulos.length) {
      graficos.push(new ChartJS(elGrupo, {
        type: 'bar',
        data: {
          labels: dados.grupo.rotulos,
          datasets: [{
            label: 'Saldo',
            data: dados.grupo.valores,
            backgroundColor: dados.grupo.valores.map((v) => (v < 0 ? c.neg : c.s1)),
            borderRadius: 4, borderSkipped: false, maxBarThickness: 22, categoryPercentage: 0.8,
          }],
        },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { ...tooltip, callbacks: { label: (/** @type {any} */ i) => ` Saldo: ${moeda.format(i.parsed.x)}` } },
          },
          scales: {
            x: {
              min: Math.min(0, ...dados.grupo.valores) * 1.1,
              max: Math.max(0, ...dados.grupo.valores) * 1.05,
              grid: { color: (/** @type {any} */ ctx) => (ctx.tick && ctx.tick.value === 0 ? c.eixo : c.grade) },
              border: { display: false },
              ticks: { callback: (/** @type {number} */ v) => moedaCurta.format(v), maxTicksLimit: 5 },
            },
            y: { grid: { display: false }, border: { display: false }, ticks: { color: c.texto, autoSkip: false } },
          },
        },
      }));
    }
  }

  desenhar();
  document.addEventListener('tema-alterado', desenhar);   // redesenha com as cores do tema

  // Escolher um atalho de período preenche as datas e envia
  const periodo = /** @type {HTMLSelectElement|null} */ (document.getElementById('fPeriodo'));
  periodo?.addEventListener('change', () => { if (periodo.value) periodo.form?.submit(); });
  ['fDe', 'fAte'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => {
    if (periodo) periodo.value = '';
  }));
})();
