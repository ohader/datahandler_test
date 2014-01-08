Work in Progress
================

This extension is not working, it's just to visualize an idea instead of a complete package.

Examples
--------

see [RegularWorkspacesTest.php](Tests/Unit/RegularWorkspacesTest.php)

```php
$first = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'First'));
$second = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'Second'));
$third = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'Third'));

// ...

$firstLocalized = $simulationService->localizeLiveElement('tt_content', $first->uid);

// ...

$simulationService->moveElementAfter('tt_content', $first->uid, $second->uid);

// ...

$this->assertSortingOrder(
	'tt_content', 'sorting',
	array(
		$first->placeholderUid,
		$second->placeholderUid,
		$third->placeholderUid,
	)
);
```
