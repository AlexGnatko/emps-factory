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
                index: {},

            };
        },
        mounted: function(){
            this.load_index();
        },

        methods: {
            load_index: function() {
                var that = this;
                this.loading = true;
                axios
                    .get("./?load_index=1")
                    .then(function(response){
                        that.loading = false;
                        var data = response.data;
                        if (data.code == 'OK') {
                            that.index = data.index;
                        } else {
                            toastr.error(data.message);
                        }
                    });
            },
        }
    });
    EMPS.load_css('/mjs/awstats/awstats.css' + css_reset);
});
