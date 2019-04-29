<?php
// determine number of days that have elapsed
$daysElapsed = $module->getProjectSetting('daysElapsed');
if (!isset($daysElapsed)) {
	$daysElapsed = 0;
	$module->setProjectSetting('daysElapsed', 0);
}
$daysElapsed = intval($daysElapsed);

// determine which sequences to send emails for
$urls = [];
$settings = $module->getProjectSettings();
foreach ($settings['sequence']['value'] as $i => $sequence) {
	$period_every = $settings['periodicity-every']['value'][$i];
	$period_end = $settings['periodicity-end']['value'][$i];
	if ($isset($period_every) and $isset($period_end)) {
		$period_every = intval($period_every);
		$period_end = intval($period_end);
		if (($daysElapsed % $period_every) == 0 and $daysElapsed <= $period_end) {
			$urls[] = $module->getUrl("interview.php") . "&NOAUTH&sequence=$sequence";
		}
	}
}

$emailSender = $settings['email-sender'];
$emailSubject = $settings['email-subject'];
$emailBody = $settings['email-body'];

if (empty($urls) or !isset($emailSender) or !isset($emailSubject) or !isset($emailBody)) {
	// increment daysElapsed
	$module->setProjectSetting('daysElapsed', $daysElapsed + 1);
	exit();
}

// prepare email body by replacing [interview-links] and [interview-urls]
$emailBody = str_replace("[interview-urls]", implode($urls, "\r\n"), $emailBody);
foreach($urls as $i => $url) {
	$urls[$i] = "<a href=\"$url\">CAT-MH Interview Link</a>";
}
$emailBody = str_replace("[interview-links]", implode($urls, "\r\n"), $emailBody);

// we have links to send so for each participant with a listed email, invite to take interview(s)
$data = \REDCap::getData($module->getProjectId(), 'array');
foreach($data as $rid => $record) {
	$eid = array_keys($record)[0];
	$addressTo = $record[$eid]['participant_email'];
	if (isset($addressTo)) {
		foreach($urls as $url) {
			$success = \REDCap::email($addressTo, $emailSender, $emailSubject, $emailBody);
			if ($success === false) {
				\REDCap::logEvent("Failed Sending Interview Email", implode([$addressTo, $emailSender, $emailSubject, $emailBody], "\n"), NULL, $rid, $eid, $module->thisProjectId());
			} else {
				\REDCap::logEvent("Sent Interview Email", implode([$addressTo, $emailSender, $emailSubject, $emailBody], "\n"), NULL, $rid, $eid, $module->thisProjectId());
			}
		}
	}
}

// increment daysElapsed
$module->setProjectSetting('daysElapsed', $daysElapsed + 1);