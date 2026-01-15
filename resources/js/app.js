import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Alpine.js
window.Alpine = Alpine;
Alpine.start();

// Chart.js
window.Chart = Chart;

// jQuery (pour DataTables)
import $ from 'jquery';
window.$ = window.jQuery = $;

// DataTables
import DataTable from 'datatables.net';
import 'datatables.net-dt';
import 'datatables.net-buttons';
import 'datatables.net-buttons-dt';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';

// JSZip pour export Excel
import JSZip from 'jszip';
window.JSZip = JSZip;

// Initialiser DataTables
window.DataTable = DataTable;

console.log('✅ CENA QuickStats - Assets locaux chargés');
console.log('✅ Chart.js:', typeof Chart !== 'undefined');
console.log('✅ Alpine:', typeof Alpine !== 'undefined');
console.log('✅ jQuery:', typeof $ !== 'undefined');
console.log('✅ DataTables:', typeof DataTable !== 'undefined');
console.log('✅ JSZip:', typeof JSZip !== 'undefined');