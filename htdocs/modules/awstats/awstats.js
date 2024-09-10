var vuev, app;

emps_scripts.push(function(){
    if (vuev === undefined) {
        vuev = new Vue();
    }
    app = new Vue({
        el: '#awstats-app',
        data: function() {
            return {
                index: {},
                loading: false,
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
});
