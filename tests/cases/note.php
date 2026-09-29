<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\DayNote;

// The day's note reads the descriptions of its entries.
check('note join one', 'Regen', DayNote::join(['  Regen ']));
check('note join several', "Regen\nUmbau", DayNote::join(['Regen', null, '', 'Umbau']));
check('note join none', null, DayNote::join([null, '  ']));

// Writing puts the note on the first entry and clears the others.
check('note assign', ['Nachdreh', null], DayNote::assign(['Regen', 'Umbau'], ' Nachdreh '));
check('note assign clear', [null], DayNote::assign(['Regen'], '  '));
check('note assign unchanged', null, DayNote::assign(['Regen', 'Umbau'], "Regen\nUmbau"));
check('note assign no entry', null, DayNote::assign([], 'Regen'));
check('note assign browser line breaks unchanged', null, DayNote::assign(["Set 3\nRegen"], "Set 3\r\nRegen"));
check('note clean long', DayNote::MAX_LENGTH, mb_strlen((string) DayNote::clean(str_repeat('ä', DayNote::MAX_LENGTH + 5))));
