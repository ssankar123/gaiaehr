<?php
/**
GaiaEHR (Electronic Health Records)
Copyright (C) 2013 Certun, inc.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Logs extends MatchaHelper
{

	/**
	 * @brief     Get all logs
	 * @details   This method will return all the logs and filter them by start and
	 * limit
	 *            to be used with the Ext.ux.SlidingPager plugin.
	 *
	 * @author    Ernesto J. Rodriguez (Certun) <erodriguez@certun.com>
	 * @version   Vega 1.0
	 *
	 * @warning   getLogs 'len' in /data/cinfig.php must be set to 1 in order for
	 * Ext.direct to send the Params
	 *
	 * @param stdClass $params Params sent from sencha
	 * @return mixed array of records with totals count
	 */
	public function getLogs(stdClass $params)
	{
		$this -> setSQL("SELECT * FROM log ORDER BY id DESC");
		$records = $this -> fetchRecords(PDO::FETCH_CLASS);
		$total = count($records);
		$rows = array_splice($records, $params -> start, $params -> limit);
		return array(
			'totals' => $total,
			'rows' => $rows
		);
	}

}
