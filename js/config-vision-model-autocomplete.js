(function() {
    var visionModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4.1',
        'gpt-4.1-mini',
        'gpt-4.1-nano',
        'o3',
        'o3-mini',
        'o1',
        'o1-mini'
    ];

    function renderOptions(datalist, term) {
        if (!datalist) {
            return;
        }

        var normalized = (term || '').trim().toLowerCase();
        var filtered = visionModels.filter(function(model) {
            return model.toLowerCase().indexOf(normalized) !== -1;
        });

        var list = filtered.length ? filtered : visionModels;
        list = list.slice(0, 10);

        while (datalist.firstChild) {
            datalist.removeChild(datalist.firstChild);
        }

        list.forEach(function(model) {
            var option = document.createElement('option');
            option.value = model;
            datalist.appendChild(option);
        });
    }

    function setup(inputId, datalistId) {
        var input = document.getElementById(inputId);
        var datalist = document.getElementById(datalistId);

        if (!input || !datalist) {
            return;
        }

        renderOptions(datalist, input.value);

        input.addEventListener('input', function() {
            renderOptions(datalist, input.value);
        });
    }

    window.AIADTVisionModelAutocomplete = {
        setup: setup
    };
})();

