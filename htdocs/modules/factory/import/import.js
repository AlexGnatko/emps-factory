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
                            toastr.success("Import initiated...");
                            $("button").blur();
                        } else {
                            toastr.error(data.message);
                        }
                    });

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
