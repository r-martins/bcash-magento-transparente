(function () {
  var cards = [ '1', '2', '37', '45', '55', '56', '63'];
  
  function clickFlag(e) {
    cardConteiner = document.getElementById('card-conteiner');
    if (isCard(this) && hasClass(cardConteiner, 'b-hide')) {
      cardConteiner.className = cardConteiner.className.replace(/\bb-hide\b/,'');
    } else if (!isCard(this) && !hasClass(cardConteiner, 'b-hide')) {
      cardConteiner.className = cardConteiner.className + " b-hide";
    }
  }

  function isCard(element) {
    return cards.indexOf(element.value) > -1;
  }

  function hasClass(element, cls) {
    return (' ' + element.className + ' ').indexOf(' ' + cls + ' ') > -1;
  }
  
  window.onload = function() {
    var payments = document.getElementsByName("payment-method");
    for (var i = payments.length - 1; i >= 0; i--) {
      var payment = payments[i];
      payment.onclick = clickFlag;
    };
  }
})();
