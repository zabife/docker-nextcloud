<?php
/**
 * Copyright (c) 2014 Vincent Petry <PVince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

class NaturalSortTest extends \Test\TestCase {

	/**
	 * @dataProvider naturalSortDataProvider
	 */
	public function testNaturalSortCompare($array, $sorted)
	{
		if(!class_exists('Collator')) {
			$this->markTestSkipped('The intl module is not available, natural sorting might not work as expected.');
			return;
		}
		$comparator = \OC\NaturalSort::getInstance();
		usort($array, array($comparator, 'compare'));
		$this->assertEquals($sorted, $array);
	}

	/**
	* @dataProvider defaultCollatorDataProvider
	*/
	public function testDefaultCollatorCompare($array, $sorted)
	{
		$comparator = new \OC\NaturalSort(new \OC\NaturalSort_DefaultCollator());
		usort($array, array($comparator, 'compare'));
		$this->assertEquals($sorted, $array);
	}

	/**
	 * Data provider for natural sorting with php5-intl's Collator.
	 * Must provide the same result as in core/js/tests/specs/coreSpec.js
	 * @return array test cases
	 */
	public function naturalSortDataProvider()
	{
		return array(
			// different casing
			array(
				// unsorted
				array(
					'aaa',
					'bbb',
					'BBB',
					'AAA'
				),
				// sorted
				array(
					'aaa',
					'AAA',
					'bbb',
					'BBB'
				)
			),
			// numbers
			array(
				// unsorted
				array(
					'124.txt',
					'abc1',
					'123.txt',
					'abc',
					'abc2',
					'def (2).txt',
					'ghi 10.txt',
					'abc12',
					'def.txt',
					'def (1).txt',
					'ghi 2.txt',
					'def (10).txt',
					'abc10',
					'def (12).txt',
					'z',
					'ghi.txt',
					'za',
					'ghi 1.txt',
					'ghi 12.txt',
					'zz',
					'15.txt',
					'15b.txt',
				),
				// sorted
				array(
					'15.txt',
					'15b.txt',
					'123.txt',
					'124.txt',
					'abc',
					'abc1',
					'abc2',
					'abc10',
					'abc12',
					'def.txt',
					'def (1).txt',
					'def (2).txt',
					'def (10).txt',
					'def (12).txt',
					'ghi.txt',
					'ghi 1.txt',
					'ghi 2.txt',
					'ghi 10.txt',
					'ghi 12.txt',
					'z',
					'za',
					'zz',
				)
			),
			// chinese characters
			array(
				// unsorted
				array(
					'???.txt',
					'???.txt',
					'???.txt',
					'??? 2.txt',
					'???.txt',
					'???.txt',
					'abc.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'??????.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'123.txt',
				),
				// sorted
				array(
					'123.txt',
					'abc.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'??? 2.txt',
					'??????.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
					'???.txt',
				)
			),
			// with umlauts
			array(
				// unsorted
				array(
					'??h.txt',
					'??h.txt',
					'oh.txt',
					'??h 2.txt',
					'??h.txt',
					'ah.txt',
					'??h.txt',
					'uh.txt',
					'??h.txt',
					'??h.txt',
				),
				// sorted
				array(
					'ah.txt',
					'??h.txt',
					'??h.txt',
					'oh.txt',
					'??h.txt',
					'??h.txt',
					'uh.txt',
					'??h.txt',
					'??h.txt',
					'??h 2.txt',
				)
			),
		);
	}

	/**
	* Data provider for natural sorting with \OC\NaturalSort_DefaultCollator.
	* Must provide the same result as in core/js/tests/specs/coreSpec.js
	* @return array test cases
	*/
	public function defaultCollatorDataProvider()
	{
		return array(
			// different casing
			array(
				// unsorted
				array(
					'aaa',
					'bbb',
					'BBB',
					'AAA'
				),
				// sorted
				array(
					'aaa',
					'AAA',
					'bbb',
					'BBB'
				)
			),
			// numbers
			array(
				// unsorted
				array(
					'124.txt',
					'abc1',
					'123.txt',
					'abc',
					'abc2',
					'def (2).txt',
					'ghi 10.txt',
					'abc12',
					'def.txt',
					'def (1).txt',
					'ghi 2.txt',
					'def (10).txt',
					'abc10',
					'def (12).txt',
					'z',
					'ghi.txt',
					'za',
					'ghi 1.txt',
					'ghi 12.txt',
					'zz',
					'15.txt',
					'15b.txt',
				),
				// sorted
				array(
					'15.txt',
					'15b.txt',
					'123.txt',
					'124.txt',
					'abc',
					'abc1',
					'abc2',
					'abc10',
					'abc12',
					'def.txt',
					'def (1).txt',
					'def (2).txt',
					'def (10).txt',
					'def (12).txt',
					'ghi.txt',
					'ghi 1.txt',
					'ghi 2.txt',
					'ghi 10.txt',
					'ghi 12.txt',
					'z',
					'za',
					'zz',
				)
			),
		);
	}
}
