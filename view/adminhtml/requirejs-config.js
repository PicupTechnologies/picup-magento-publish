var config = {
    paths: {
        'bloodhound': "Picup_Shipping/js/bloodhound",
        'typeahead': "Picup_Shipping/js/typeahead",
        'bootstraptypeahead': "Picup_Shipping/js/bootstraptypeahead",
    },
    shim: {
        'typeahead': {
            deps: ['jquery'],
            init: function ($) {
                return require.s.contexts._.registry['typeahead.js'].factory( $ );
            }
        },
        'bloodhound': {
            deps: ['jquery'],
            exports: 'Bloodhound'
        },
        'bootstraptypeahead': {
            deps: ['jquery', 'bloodhound'],
            init: function ($) {

            }
        }
    }
};
