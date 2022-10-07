var vuev, app;

emps_scripts.push(function(){
    if (vuev === undefined) {
        vuev = new Vue();
    }
    app = new Vue({
        el: '#services-app',
        data: function() {
            return {
                mode: 'new',
                editrow: {},
                loading: false,
                lst: [],
            };
        },
        mounted: function(){
            this.load_list();
        },

        methods: {
            load_list: function() {
                var that = this;
                this.loading = true;
                axios
                    .get("./?load_list=1")
                    .then(function(response){
                        that.loading = false;
                        var data = response.data;
                        if (data.code == 'OK') {
                            that.lst = data.lst;
                        } else {
                            toastr.error(data.message);
                        }
                    });
            },
            submit_form: function() {
                var that = this;
                this.loading = true;
                var row = {};
                if (this.mode == 'new') {
                    row.post_add_service = 1;
                } else {
                    row.post_save_service = 1;
                }

                row.payload = this.editrow;

                axios
                    .post("./", row)
                    .then(function(response){
                        var data = response.data;

                        if(data.code == 'OK'){
                            $("button").blur();
                            toastr.success("Changes saved!");
                            that.loading = false;
                            that.load_list();
                        } else {
                            toastr.error(data.message);
                        }
                    });

            },
            edit_row: function(row) {
                this.editrow = row;
                this.mode = 'edit';
            }
        }
    });
});
