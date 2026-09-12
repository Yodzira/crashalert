/* CrashAlert settings wizard helpers */
(function ($) {
  'use strict';
  function test(channel, btn, result) {
    $(btn).on('click', function (e) {
      e.preventDefault();
      $(result).text(window.wpcaI18n.testing);
      $.post(window.ajaxurl, {
        action: 'wpca_test',
        nonce: wpcaSettings.nonce,
        channel: channel
      }).done(function (r) {
        $(result).text(r && r.success ? window.wpcaI18n.ok : (r && r.data && r.data.msg ? window.wpcaI18n.failed + ' [' + r.data.msg + ']' : window.wpcaI18n.failed));
      }).fail(function () {
        $(result).text(window.wpcaI18n.error);
      });
    });
  }
  $(function () {
    if (typeof window.wpcaI18n === 'undefined') { return; }
    test('telegram', '#wpca-test-tg', '#wpca-test-tg-result');
    test('webhook', '#wpca-test-webhook', '#wpca-test-webhook-result');
  });
})(jQuery);
