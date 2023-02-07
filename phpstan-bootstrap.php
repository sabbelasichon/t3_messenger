<?php

class_alias(
    \Ssch\T3Messenger\Mailer\Event\AfterMailerSentMessageEvent::class,
    \TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent::class
);

class_alias(
    \Ssch\T3Messenger\Mailer\Event\BeforeMailerSentMessageEvent::class,
    \TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent::class
);

class_alias(
    \Ssch\T3Messenger\Mailer\Contract\MailerInterface::class,
    \TYPO3\CMS\Core\Mail\MailerInterface::class
);
