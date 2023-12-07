define(['require', 'TYPO3/CMS/Backend/Modal', "TYPO3/CMS/Core/Event/RegularEvent", "TYPO3/CMS/Backend/Enum/Severity", ], function(require, Modal, Event, Severity) {
    "use strict";
    return new class {
        constructor() {
            this.selector = ".js-t3-messenger-remove-message", this.initialize()
        }

        initialize() {
            new Event("click", (function (e) {
                e.preventDefault();
                Modal.confirm(this.dataset.modalTitle, this.dataset.modalQuestion, Severity.SeverityEnum.warning, [{
                    text: this.dataset.modalCancel,
                    active: !0,
                    btnClass: "btn-default",
                    name: "cancel"
                }, {text: this.dataset.modalOk, btnClass: "btn-warning", name: "delete"}]).on("button.clicked", e => {
                    "delete" === e.target.getAttribute("name") && (window.location.href = this.getAttribute("href")), Modal.dismiss()
                })
            })).delegateTo(document, this.selector)
        }
    }
});
