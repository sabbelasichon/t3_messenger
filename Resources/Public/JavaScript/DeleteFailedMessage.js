define(['require', 'TYPO3/CMS/Backend/Modal', "TYPO3/CMS/Core/Event/RegularEvent", "TYPO3/CMS/Backend/Enum/Severity", "TYPO3/CMS/Core/Ajax/AjaxRequest", "TYPO3/CMS/T3Messenger/RefreshFailedMessages"], function (require, Modal, Event, Severity, AjaxRequest, RefreshFailedMessages) {
    "use strict";
    return new class {
        constructor() {
            this.selector = ".js-t3-messenger-remove-message", this.initialize()
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
                    name: "delete"
                }]).on("button.clicked", function (e) {
                    if ("delete" === e.target.getAttribute("name")) {
                        var payload = {'id': anchor.dataset.messageId, 'transport': anchor.dataset.messageTransport};
                        new AjaxRequest(TYPO3.settings.ajaxUrls.t3_messenger_failed_messages_delete)
                            .delete(JSON.stringify(payload))
                            .then(async function () {
                                Modal.dismiss();
                                RefreshFailedMessages.refresh();
                            });
                    } else {
                        Modal.dismiss();
                    }
                })
            })).delegateTo(document, this.selector)
        }
    }
});
