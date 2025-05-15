var vuev, app;

emps_scripts.push(function(){
    if (vuev === undefined) {
        vuev = new Vue();
    }
    app = new Vue({
        el: '#awstats-app',
        mixins: [EMPS_common_mixin],
        data: function() {
            return {
                lst: [],
            };
        },
        mounted: function(){
            this.lst = window.lst;
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
            open_log_cat: function(text) {
                this.log = "Loading...";
                this.ips = [];
                this.open_modal("modalLog");
                axios.get("./?load_log=1&text=" + encodeURIComponent(text))
                    .then(response => {
                        let data = response.data;
                        if (data.code == "OK") {
                            this.log = data.log;
                            this.ips = data.ips;
                        } else {
                            toastr.error(data.message);
                        }
                    });
            },
            open_ip: function(ip) {
                this.ip = ip;
                this.open_modal("modalIP");
                axios.get("./?checkip=1&ip=" + ip)
                    .then(response => {
                        let data = response.data;
                        if (data.code == "OK") {
                            if (data.banned) {
                                toastr.error("The IP " + ip + " is banned");
                            } else {
                                toastr.success("The IP " + ip + " is not banned");
                            }
                        } else {
                            toastr.error(data.message);
                        }
                    });
            },
            ban: function(ip, mode) {
                axios.get("./?banip=1&ip=" + ip + "&mode=" + mode)
                    .then(response => {
                        this.close_modal("modalIP");
                        let data = response.data;
                        if (data.code == "OK") {
                            if (mode == 1) {
                                toastr.success("IP " + ip + " has been banned!");
                            } else {
                                toastr.success("IP " + ip + " has been unbanned!");
                            }
                        } else {
                            toastr.error(data.message);
                        }
                    });
            }
        }
    });
    EMPS.load_css('/mjs/awstats/awstats.css' + css_reset);
});
