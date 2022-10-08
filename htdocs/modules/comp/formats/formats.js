Vue.filter('form_local_time', function (value) {
    if (value == 0) {
        return "-";
    }
    var m = moment.unix(value);
    return m.format("DD.MM.YYYY HH:mm");
});

Vue.filter('form_only_time', function (value) {
    if (value == 0) {
        return "-";
    }
    var m = moment.unix(value);
    return m.format("HH:mm");
});

Vue.filter('form_local_time_long', function (value) {
    if (value == 0) {
        return "-";
    }
    var m = moment.unix(value);
    return m.format("DD.MM.YYYY HH:mm:ss");
});

Vue.filter('form_local_date', function (value) {
    if (value == 0) {
        return "-";
    }
    var m = moment.unix(value);
    return m.format("DD.MM.YYYY");
});

Vue.filter('form_long_date', function (value) {
    if (value == 0) {
        return "-";
    }
    var m = moment.unix(value);
    return m.format("DD MMMM YYYY");
});

Vue.filter('short_money', function(value) {
    if (value < 1000) {
        return Math.round(value).toString();
    }
    value /= 1000;
    if (value < 1000) {
        return Math.round(value).toString() + "K";
    }
    value /= 1000;
    if (value < 1000) {
        return Math.round(value).toString() + "M";
    }
    value /= 1000;
    if (value < 1000) {
        return Math.round(value).toString() + "B";
    }
    value /= 1000;
    return Math.round(value).toString() + "T";
});

Vue.filter('milli', function (v) {
    if (v === undefined) {
        return '-';
    }

    if (v == 0) {
        return '-';
    }

    var rv;
    var number = v;
    number = parseFloat(number);
    number = (Math.round(number * 1000)) / 1000;
    var int = Math.floor(number);
    var dec = number - int;
    if(dec > 0){
        rv = new Intl.NumberFormat('ru-RU').format(number, {minimumFractionDigits: 0, maximumFractionDigits: 3});
    }else{
        rv = new Intl.NumberFormat('ru-RU').format(number, {maximumFractionDigits: 0});
    }
    return rv;
});

Vue.filter('money', function (v) {
    if (v === undefined) {
        return '-';
    }

    if (v == 0) {
        return '-';
    }

    var rv;
    var number = v;
    number = parseFloat(number);
    number = (Math.round(number * 100)) / 100;
    var int = Math.floor(number);
    var dec = number - int;
    if(dec > 0){
        rv = new Intl.NumberFormat('ru-RU').format(number, {minimumFractionDigits: 0, maximumFractionDigits: 2});
    }else{
        rv = new Intl.NumberFormat('ru-RU').format(number, {maximumFractionDigits: 0});
    }
    return rv;
});

Vue.filter('rate', function (number) {
    var rv;
    number = parseFloat(number);
    number = (Math.round(number * 100)) / 100;
    var int = Math.floor(number);
    var dec = number - int;
    if(dec > 0){
        rv = new Intl.NumberFormat('ru-RU').format(number, {minimumFractionDigits: 0, maximumFractionDigits: 3});
    }else{
        rv = new Intl.NumberFormat('ru-RU').format(number, {maximumFractionDigits: 0});
    }
    return rv;
});

Vue.filter('nl2br', function (txt) {
    txt = txt.replace(/\n/g, "<br/>");
    return txt;
});

Vue.filter('reverse', function(a) {
    var ar = a.filter(() => true);
    return ar.reverse();
});