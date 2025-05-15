var vuev, app;

emps_scripts.push(function(){
    if (vuev === undefined) {
        vuev = new Vue();
    }
    app = new Vue({
        el: '#awstats-app',
        mixins: [EMPS_common_mixin, awstats_mixin],
        data: function() {
            return {
                lst: [],
            };
        },
        mounted: function(){
            this.lst = window.lst;
        },

        methods: {

        }
    });
    EMPS.load_css('/mjs/awstats/awstats.css' + css_reset);
});
