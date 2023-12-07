define(['require', 'TYPO3/CMS/Backend/Modal', "TYPO3/CMS/Core/Event/RegularEvent", "TYPO3/CMS/Backend/Enum/Severity", "TYPO3/CMS/Core/Ajax/AjaxRequest", "TYPO3/CMS/T3Messenger/RefreshFailedMessages"], function (require, Modal, Event, Severity, AjaxRequest, RefreshWidget) {
    "use strict";
    return new class {
        constructor() {
            this.selector = ".js-t3-messenger-retry-message", this.initialize()
        }

        initialize() {
            new Event("click", (function (e) {
                e.preventDefault();
                var anchor = this;
                Modal.confirm(this.dataset.modalTitle, this.dataset.modalQuestion, Severity.SeverityEnum.warning, [{
                    text: this.dataset.modalCancel,
                    active: !0,
                    btnClass: "btn-default",
                    name: "cancel"
                }, {
                    text: this.dataset.modalOk,
                    btnClass: "btn-warning",
                    name: "retry"
                }]).on("button.clicked", function (e) {
                    if ("retry" === e.target.getAttribute("name")) {
                        var payload = {'id': anchor.dataset.messageId, 'transport': anchor.dataset.messageTransport};
                        new AjaxRequest(TYPO3.settings.ajaxUrls.t3_messenger_failed_messages_retry)
                            .post(JSON.stringify(payload))
                            .then(async function () {
                                Modal.dismiss();
                                RefreshWidget.refresh();
                            });
                    } else {
                        Modal.dismiss();
                    }
                })
            })).delegateTo(document, this.selector)
        }
    }
});
