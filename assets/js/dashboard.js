/* ============================================================
   assets/js/dashboard.js
   Scripts exclusivos del Dashboard
   ============================================================ */

// ── Gráfica de dona — Servicios del Mes ──────────────────
const ctx = document.getElementById('chartServicios').getContext('2d');
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Cambio Aceite', 'Frenos', 'Afinamiento', 'Diagnóstico', 'Otros'],
    datasets: [{
      data: [35, 25, 20, 12, 8],
      backgroundColor: ['#1d6fa4','#e8820c','#27ae60','#8e44ad','#f39c12'],
      borderWidth: 0,
      hoverOffset: 6
    }]
  },
  options: {
    cutout: '65%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { font: { size: 11 }, padding: 12 }
      }
    }
  }
});