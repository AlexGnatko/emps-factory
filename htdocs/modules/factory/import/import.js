var vuev, app;

emps_scripts.push(function(){
    vuev = new Vue();
    app = new Vue({
        el: '#import-app',
        mixins: [],
        data: function() {
            return {
                local_website_id: 0,
                remote_factory: '',
                remote_hostname: '',
                log: "",
                ls_cache: {},
                command_id: 0,
                local_command_id: 0,
                status: 0,
                command_payload: {},
            }
        },
        mounted: function(){
            $("#import-app").show();
            $(".app-loading").hide();
            this.remote_factory = this.ls_get("remote_factory", "");
            this.remote_hostname = this.ls_get("remote_hostname", "");
            this.local_website_id = this.ls_get("local_website_id", 0);
        },
        methods: {
            ls_get: function(name, def) {
                if (this.ls_cache[name] !== undefined) {
                    return this.ls_cache[name];
                }
                var current = localStorage.getItem(name);
                current = JSON.parse(current);
                if ((typeof current) == (typeof def)) {
                    return current;
                }
                return def;
            },
            ls_set: function(name, value) {
                if (this.ls_cache[name] !== value) {
                    var text = JSON.stringify(value);
                    localStorage.setItem(name, text);
                }
                this.$set(this.ls_cache, name, value);
            },
            initiate_import: function() {
                this.ls_set("remote_factory", this.remote_factory);
                this.ls_set("remote_hostname", this.remote_hostname);
                this.ls_set("local_website_id", this.local_website_id);

                var data = {};
                data.remote_factory = this.remote_factory;
                data.remote_hostname = this.remote_hostname;
                data.local_website_id = this.local_website_id;

                var that = this;

                axios
                    .post("./", {post_initiate: true, payload: data})
                    .then(function(response){
                        var data = response.data;

                        if(data.code == 'OK'){
                            toastr.success("Export initiated...");
                            that.log = "";
                            that.add_log("Creating export files at the remote factory...");
                            that.command_id = data.command;
                            setTimeout(that.check_status, 500);
                            $("button").blur();
                        } else {
                            toastr.error(data.message);
                        }
                    });

            },
            check_status: function() {
                var that = this;
                axios
                    .get("./?load_status=1&command=" + this.command_id + "&hostname=" +
                        this.remote_hostname + "&factory=" + this.remote_factory)
                    .then(function(response){
                        var data = response.data;

                        if (data.code == 'OK') {
                            if (data.status < 10) {
                                that.log += ".";
                                setTimeout(that.check_status, 2000);
                            } else {
                                that.add_log("\r\nExport done, importing...");
                                that.command_payload = data;
                                that.continue_import();
                            }
                        }else{
                            console.log("Error: " + data.message);
                        }
                    });
            },
            check_local_status: function() {
                var that = this;
                axios
                    .get("./?load_local_status=1&command=" + this.local_command_id)
                    .then(function(response){
                        var data = response.data;

                        if (data.code == 'OK') {
                            if (data.status < 10) {
                                that.log += ".";
                                setTimeout(that.check_local_status, 2000);
                            } else {
                                toastr.success("Import done!");
                                that.add_log("\r\nImport done!");
                            }
                        }else{
                            console.log("Error: " + data.message);
                        }
                    });
            },
            continue_import: function() {
                var data = {};
                data.remote_factory = this.remote_factory;
                data.remote_hostname = this.remote_hostname;
                data.local_website_id = this.local_website_id;
                data.import_payload = this.command_payload;

                var that = this;

                axios
                    .post("./", {post_continue: true, payload: data})
                    .then(function(response){
                        var data = response.data;

                        if(data.code == 'OK'){
                            toastr.success("Import initiated...");
                            that.add_log("Downloading files to the local factory...");
                            that.local_command_id = data.command;
                            setTimeout(that.check_local_status, 500);
                            $("button").blur();
                        } else {
                            toastr.error(data.message);
                        }
                    });
            },
            add_log: function(msg) {
                this.log += msg + "\r\n";
            }
        },
        watch: {


        },
        created: function() {


        },
        beforeDestroy: function() {

        },

    });
});
