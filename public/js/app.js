$(document).ready(function(){
    $(".orderToClick").click(function() {
        var orderId = $(this).attr('data-orderId');
        console.log(orderId);
        $(".order"+orderId).toggle();
    });
});