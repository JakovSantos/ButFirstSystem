/* Printable reports: open the browser print dialog once the page has loaded,
   and wire the Print / Close buttons. (Kept in its own file, with no inline
   onclick handlers, so the Content-Security-Policy can forbid inline scripts.) */
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
document.addEventListener('DOMContentLoaded', function () {
    var print = document.getElementById('btnPrintNow');
    var close = document.getElementById('btnClosePrint');
    if (print) { print.addEventListener('click', function () { window.print(); }); }
    if (close) { close.addEventListener('click', function () { window.close(); }); }
});
