window.deftrackDebounce = (f, d = 275) => {
    let t;
    return (...a) => {
        clearTimeout(t);
        t = setTimeout(() => f(...a), d);
    };
};
// Chart.js safe creator: destroy old instance before re-create
window.deftrackCharts = window.deftrackCharts || {};
window.makeDeftrackChart = function (canvasId, config) {
    try {
        if (window.deftrackCharts[canvasId]) {
            window.deftrackCharts[canvasId].destroy();
        }
    } catch (e) {}
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    const ch = new Chart(ctx, config);
    window.deftrackCharts[canvasId] = ch;
    return ch;
};
