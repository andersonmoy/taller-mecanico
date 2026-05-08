/* ============================================================
   assets/js/main.js
   Scripts GLOBALES — Sistema Taller Mecánico
   ============================================================ */

// ── Mostrar fecha y hora actual en el topbar ──────────────
function actualizarReloj() {
  const el = document.getElementById('reloj');
  if (!el) return;
  const ahora = new Date();
  const opciones = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
  el.textContent = ahora.toLocaleDateString('es-PE', opciones).replace(',', ' —');
}
setInterval(actualizarReloj, 1000);
actualizarReloj();

// ── Confirmar antes de eliminar ───────────────────────────
function confirmarEliminar(mensaje = '¿Estás seguro de eliminar este registro?') {
  return confirm(mensaje);
}

// ── Cerrar alertas automáticamente ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const alertas = document.querySelectorAll('.alert-auto');
  alertas.forEach(alerta => {
    setTimeout(() => {
      alerta.style.opacity = '0';
      alerta.style.transition = 'opacity .5s';
      setTimeout(() => alerta.remove(), 500);
    }, 4000);
  });
});

// ── Marcar menú activo según URL ─────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const url = window.location.pathname;
  document.querySelectorAll('.menu-item').forEach(item => {
    if (item.getAttribute('href') && url.includes(item.getAttribute('href').replace('../..', ''))) {
      item.classList.add('active');
    }
  });
});

// ── Formatear número como moneda peruana ─────────────────
function formatearSoles(numero) {
  return 'S/ ' + parseFloat(numero).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── Calcular IGV automáticamente en formularios ──────────
function calcularIGV(precioSinIGV) {
  const igv = parseFloat(precioSinIGV) * 0.18;
  const total = parseFloat(precioSinIGV) + igv;
  return { igv: igv.toFixed(2), total: total.toFixed(2) };
}