var logs_mixin =
    {
        data: function() {
            return {
                log: "",
                expanded: false,
                current_log: null,
                logs: [
                    {name: "Access Log", log: ""},
                    {name: "Error Log", log: "error"},
                ],
            };
        },
        mounted: function(){
            this.current_log = this.logs[0];
            this.tick();
            setTimeout(this.scroll_down, 1000);
        },
        methods: {
            select_log: function(log) {
                this.current_log = log;
                this.tick();
                setTimeout(this.scroll_down, 1000);
            },
            tick: function() {
                var that = this;
                axios
                    .get("./?tick=1&path=" + this.current_log.log)
                    .then(function(response){
                        var data = response.data;
                        if (data.code == 'OK') {
                            that.log = data.log;
                            that.autoscroll();
                        }
                    });
            },
            scroll_down: function() {
                this.$refs.logfile.scrollTop = this.$refs.logfile.scrollHeight;
            },
            autoscroll: function() {
                let o = this.$refs.logfile;
                if (o.scrollTop > ((o.scrollHeight - o.clientHeight) * 0.95)) {
                    this.scroll_down();
                }
            },
            toggle_expanded: function() {
                if (!this.expanded) {
                    this.expanded = true;
                } else {
                    this.expanded = false;
                }
            }
        },
        computed: {

        },
        created: function () {
            this.interval = setInterval(this.tick, 2000);
        },
        destroyed: function () {
            clearInterval(this.interval);
        },
    };


EMPS.load_css('/mjs/admin-logs/logs.css' + css_reset);

var vuev, app;

emps_scripts.push(function(){
    vuev = new Vue();
    app = new Vue({
        el: '#logs-app',
        mixins: [logs_mixin],
        data: function() {
            return {

            }
        },
        mounted: function(){

        },
        methods: {

        },
        watch: {


        },
        created: function() {


        },
        beforeDestroy: function() {

        },

    });
});
