// Mapa do percurso (Leaflet + OpenStreetMap). Os dados vêm de data-track (JSON gerado pelo servidor).
(() => {
  'use strict';
  const el = document.querySelector('[data-track]');
  if (!el || typeof L === 'undefined') return;
  let pts;
  try { pts = JSON.parse(el.dataset.track); } catch (_) { return; }
  if (!Array.isArray(pts) || pts.length < 2) return;
  const latlngs = pts.filter((p) => Array.isArray(p) && isFinite(p[0]) && isFinite(p[1])).map((p) => [p[0], p[1]]);

  const map = L.map(el, { scrollWheelZoom: false, tap: true });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; colaboradores do <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
  }).addTo(map);
  const line = L.polyline(latlngs, { color: '#ff7a1a', weight: 4, opacity: 0.9 }).addTo(map);
  L.circleMarker(latlngs[0], { radius: 8, color: '#fff', weight: 2, fillColor: '#46a758', fillOpacity: 1 }).addTo(map).bindTooltip('Largada');
  L.circleMarker(latlngs[latlngs.length - 1], { radius: 8, color: '#fff', weight: 2, fillColor: '#e5484d', fillOpacity: 1 }).addTo(map).bindTooltip('Chegada');
  map.fitBounds(line.getBounds(), { padding: [20, 20] });
  // habilita zoom com scroll só depois de clicar no mapa (não atrapalha a rolagem da página)
  map.once('click', () => map.scrollWheelZoom.enable());
})();
