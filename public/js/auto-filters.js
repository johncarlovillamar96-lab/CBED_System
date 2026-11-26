document.querySelectorAll('form[data-auto-filters]').forEach(form => {
    let timer;
    const isTypedFilter = control => control.matches('input:is([type="search"], [type="text"], [type="number"], :not([type]))');

    function apply() {
        clearTimeout(timer);
        if (form.checkValidity()) form.requestSubmit();
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(apply, 500);
    }

    form.addEventListener('input', event => {
        if (isTypedFilter(event.target) && !event.isComposing) schedule();
    });
    form.addEventListener('compositionstart', () => clearTimeout(timer));
    form.addEventListener('compositionend', event => {
        if (isTypedFilter(event.target)) schedule();
    });
    form.addEventListener('change', event => {
        if (event.target.matches('select, input') && !isTypedFilter(event.target)) apply();
    });
    form.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.isComposing && event.target.matches('input')) {
            event.preventDefault();
            apply();
        }
    });
    form.addEventListener('submit', () => clearTimeout(timer));
});
