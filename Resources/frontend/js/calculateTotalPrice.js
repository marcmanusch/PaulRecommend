$('.selectproduct').on("change", function() {
    var totalPrice = 0;

    $('.selectproduct:checked').each(function() {
        totalPrice += parseFloat($(this).data('price'), 10);
    });

    $('.gesamtpreis').html(totalPrice);
});

