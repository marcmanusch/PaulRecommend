$(function () {
    $("#checkbox1").click(function () {
        if ($(this).is(":checked")) {
            $("#item1").show();
        } else {
            $("#item1").hide();
        }
    });

    $("#checkbox2").click(function () {
        if ($(this).is(":checked")) {
            $("#item2").show();
            $("#item2plus").show();

        } else {
            $("#item2").hide();
            $("#item2plus").hide();
        }
    });

    $("#checkbox3").click(function () {
        if ($(this).is(":checked")) {
            $("#item3").show();
            $("#item3plus").show();
        } else {
            $("#item3").hide();
            $("#item3plus").hide();
        }
    });

    $("#checkbox4").click(function () {
        if ($(this).is(":checked")) {
            $("#item4").show();
            $("#item4plus").show();
        } else {
            $("#item4").hide();
            $("#item4plus").hide();
        }
    });

    $("#checkbox5").click(function () {
        if ($(this).is(":checked")) {
            $("#item5").show();
            $("#item5plus").show();
        } else {
            $("#item5").hide();
            $("#item5plus").hide();
        }
    });

    $("#checkbox6").click(function () {
        if ($(this).is(":checked")) {
            $("#item6").show();
            $("#item6plus").show();
        } else {
            $("#item6").hide();
            $("#item6plus").hide();
        }
    });

    $("#checkbox7").click(function () {
        if ($(this).is(":checked")) {
            $("#item7").show();
            $("#item7plus").show();
        } else {
            $("#item7").hide();
            $("#item7plus").hide();
        }
    });
});