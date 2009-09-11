<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module extension (addition to function menu) 'Site Crawler' for the 'crawler' extension.
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 */

require_once(PATH_t3lib.'class.t3lib_pagetree.php');
require_once(PATH_t3lib.'class.t3lib_extobjbase.php');

require_once(t3lib_extMgm::extPath('crawler').'class.tx_crawler_lib.php');
require_once t3lib_extMgm::extPath('crawler').'domain/process/class.tx_crawler_domain_process_repository.php';
require_once t3lib_extMgm::extPath('crawler').'view/process/class.tx_crawler_view_process_list.php';
require_once t3lib_extMgm::extPath('crawler').'view/class.tx_crawler_view_pagination.php';


/**
 * Crawler backend module
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_crawler
 */
class tx_crawler_modfunc1 extends t3lib_extobjbase {

		// Internal, dynamic:
	var $duplicateTrack = array();
	var $submitCrawlUrls = FALSE;
	var $downloadCrawlUrls = FALSE;

	var $scheduledTime = 0;
	var $reqMinute = 0;

	/**
	 * @var array holds the selection of configuration from the configuration selector box
	 */
	var $incomingConfigurationSelection = array();

	/**
	 * @var tx_crawler_lib
	 */
	var $crawlerObj;

	var $CSVaccu = array();
	var $downloadUrls = array();

	/**
	 * Holds the configuration from ext_conf_template loaded by loadExtensionSettings()
	 *
	 * @var array
	 */
	protected $extensionSettings = array();

	/**
	 * Additions to the function menu array
	 *
	 * @return	array		Menu array
	 */
	function modMenu()	{
		global $LANG;

		return array (
			'depth' => array(
				0 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_0'),
				1 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_1'),
				2 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_2'),
				3 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_3'),
				4 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_4'),
				99 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_infi'),
			),
			'crawlaction' => array(
				'start' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.start'),
				'log' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.log'),
				'multiprocess' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.multiprocess')
			),
			'log_resultLog' => '',
			'log_feVars' => '',
			'processListMode' => '',
			'log_display' => array(
				'all' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.all'),
				'pending' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.pending'),
				'finished' => $LANG->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.finished')
			)
		);
	}

	/**
	 * Load extension settings
	 *
	 * @param void
	 * @return void
	 */
	protected function loadExtensionSettings() {
		$this->extensionSettings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['crawler']);
	}

	/**
	 * Main function
	 *
	 * @return	string		HTML output
	 */
	function main()	{
		global $LANG, $BACK_PATH;

		$this->incLocalLang();

		$this->loadExtensionSettings();
		if (empty($this->pObj->MOD_SETTINGS['processListMode'])) {
			$this->pObj->MOD_SETTINGS['processListMode'] = 'simple';
		}

			// Set CSS styles specific for this document:
		$this->pObj->content = str_replace('/*###POSTCSSMARKER###*/','
			TABLE.c-list TR TD { white-space: nowrap; vertical-align: top; }
		',$this->pObj->content);

		$this->pObj->content .= '<style type="text/css"><!--
			table.url-table,
			table.param-expanded,
			table.crawlerlog {
				border-bottom: 1px solid grey;
				border-spacing: 0;
				border-collapse: collapse;
			}
			table.crawlerlog td,
			table.url-table td {
				border: 1px solid lightgrey;
				border-bottom: 1px solid grey;
				 white-space: nowrap; vertical-align: top;
			}
		--></style>
		<link rel="stylesheet" type="text/css" href="'.$BACK_PATH.'../typo3conf/ext/crawler/template/res.css" />
		';

			// Type function menu:
		$h_func = t3lib_BEfunc::getFuncMenu(
			$this->pObj->id,
			'SET[crawlaction]',
			$this->pObj->MOD_SETTINGS['crawlaction'],
			$this->pObj->MOD_MENU['crawlaction'],
			'index.php'
		);

		/*
			// Showing depth-menu in certain cases:
		if ($this->pObj->MOD_SETTINGS['crawlaction']!=='cli' && $this->pObj->MOD_SETTINGS['crawlaction']!== 'multiprocess' && ($this->pObj->MOD_SETTINGS['crawlaction']!=='log' || $this->pObj->id))	{
			$h_func .= t3lib_BEfunc::getFuncMenu(
				$this->pObj->id,
				'SET[depth]',
				$this->pObj->MOD_SETTINGS['depth'],
				$this->pObj->MOD_MENU['depth'],
				'index.php'
			);
		}
		*/

			// Additional menus for the log type:
		if ($this->pObj->MOD_SETTINGS['crawlaction']==='log')	{
			$h_func .= t3lib_BEfunc::getFuncMenu(
				$this->pObj->id,
				'SET[depth]',
				$this->pObj->MOD_SETTINGS['depth'],
				$this->pObj->MOD_MENU['depth'],
				'index.php'
			);
			$h_func.= '<hr/>'.
					$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.display').': '.t3lib_BEfunc::getFuncMenu($this->pObj->id,'SET[log_display]',$this->pObj->MOD_SETTINGS['log_display'],$this->pObj->MOD_MENU['log_display'],'index.php','&setID='.t3lib_div::_GP('setID')).' - '.
					$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.showresultlog').': '.t3lib_BEfunc::getFuncCheck($this->pObj->id,'SET[log_resultLog]',$this->pObj->MOD_SETTINGS['log_resultLog'],'index.php','&setID='.t3lib_div::_GP('setID')).' - '.
					$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.showfevars').': '.t3lib_BEfunc::getFuncCheck($this->pObj->id,'SET[log_feVars]',$this->pObj->MOD_SETTINGS['log_feVars'],'index.php','&setID='.t3lib_div::_GP('setID'));
		}

		$theOutput.= $this->pObj->doc->spacer(5);
		$theOutput.= $this->pObj->doc->section($LANG->getLL('title'), $h_func, 0, 1);


			// Branch based on type:
		switch((string)$this->pObj->MOD_SETTINGS['crawlaction'])	{
			case 'start':

				if (empty($this->pObj->id)) {
					$theOutput .= '<br />'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.noPageSelected');
				} else {
					$theOutput .= $this->pObj->doc->section('',$this->drawURLs(),0,1);
				}
			break;
			case 'log':
				$theOutput .= $this->pObj->doc->section('',$this->drawLog(),0,1);
			break;
			case 'cli':
				$theOutput .= $this->pObj->doc->section('',$this->drawCLIstatus(),0,1);
			break;
			case 'multiprocess':
				$theOutput .= $this->pObj->doc->section('',$this->drawProcessOverviewAction(),0,1);
			break;
		}

		return $theOutput;
	}












	/*******************************
	 *
	 * Generate URLs for crawling:
	 *
	 ******************************/

	/**
	 * Produces a table with overview of the URLs to be crawled for each page
	 *
	 * @return	string		HTML output
	 */
	function drawURLs()	{
		global $BACK_PATH;

			// Init:
		$this->duplicateTrack = array();
		$this->submitCrawlUrls = t3lib_div::_GP('_crawl');
		$this->downloadCrawlUrls = t3lib_div::_GP('_download');

		switch((string)t3lib_div::_GP('tstamp'))	{
			case 'midnight':
				$this->scheduledTime = mktime(0,0,0);
			break;
			case '04:00':
				$this->scheduledTime = mktime(0,0,0)+4*3600;
			break;
			case 'now':
			default:
				$this->scheduledTime = time();
			break;
		}
		// $this->reqMinute = t3lib_div::intInRange(t3lib_div::_GP('perminute'),1,10000);
		// TODO: check relevance
		$this->reqMinute = 1000;


		$this->incomingConfigurationSelection = t3lib_div::_GP('configurationSelection');
		$this->incomingConfigurationSelection = is_array($this->incomingConfigurationSelection) ? $this->incomingConfigurationSelection : array('');

		$this->crawlerObj = t3lib_div::makeInstance('tx_crawler_lib');
		$this->crawlerObj->setAccessMode('gui');
		$this->crawlerObj->setID = t3lib_div::md5int(microtime());

		if (empty($this->incomingConfigurationSelection)
			|| (count($this->incomingConfigurationSelection)==1 && empty($this->incomingConfigurationSelection[0]))
			) {
			$code= '
			<tr>
				<td colspan="7"><b>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.noConfigSelected').'</b></td>
			</tr>';
		} else {
			if($this->submitCrawlUrls){
				$reason = new tx_crawler_domain_reason();
				$reason->setReason(tx_crawler_domain_reason::REASON_GUI_SUBMIT);
				tx_crawler_domain_events_dispatcher::getInstance()->post(	'invokeQueueChange',
																			$this->findCrawler()->setID,
																			array(	'reason' => $reason ));
			}

			$code = $this->crawlerObj->getPageTreeAndUrls(
				$this->pObj->id,
				$this->pObj->MOD_SETTINGS['depth'],
				$this->scheduledTime,
				$this->reqMinute,
				$this->submitCrawlUrls,
				$this->downloadCrawlUrls,
				array(), // Do not filter any processing instructions
				$this->incomingConfigurationSelection
			);


		}

		$this->downloadUrls = $this->crawlerObj->downloadUrls;
		$this->duplicateTrack = $this->crawlerObj->duplicateTrack;

		$output = '';
		if ($code)	{

			$output .= '<h3>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.configuration').':</h3>';
			$output .= '<input type="hidden" name="id" value="'.intval($this->pObj->id).'" />';

			if (!$this->submitCrawlUrls)	{
				$output .= $this->drawURLs_cfgSelectors().'<br />';
				$output .= '<input type="submit" name="_update" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.triggerUpdate').'" /> ';
				$output .= '<input type="submit" name="_crawl" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.triggerCrawl').'" /> ';
				$output .= '<input type="submit" name="_download" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.triggerDownload').'" /><br /><br />';
				$output .= $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.count').': '.count(array_keys($this->duplicateTrack)).'<br />';
				$output .= $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.curtime').': '.date('H:i:s',time()).'<br />';
				$output .= '<br />
					<table class="lrPadding c-list url-table">'.
						$this->drawURLs_printTableHeader().
						$code.
					'</table>';
			} else {
				$output .= count(array_keys($this->duplicateTrack)).' '.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.submitted').'. <br /><br />';
				$output .= '<input type="submit" name="_" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.continue').'" />';
				$output .= '<input type="submit" onclick="this.form.elements[\'SET[crawlaction]\'].value=\'log\';" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.continueinlog').'" />';
			}
		}

			// Download Urls to crawl:
		if ($this->downloadCrawlUrls)	{

				// Creating output header:
			$mimeType = 'application/octet-stream';
			Header('Content-Type: '.$mimeType);
			Header('Content-Disposition: attachment; filename=CrawlerUrls.txt');

				// Printing the content of the CSV lines:
			echo implode(chr(13).chr(10),$this->downloadUrls);

				// Exits:
			exit;
		}

			// Return output:
		return 	$output;
	}

	/**
	 * Draws the configuration selectors for compiling URLs:
	 *
	 * @return	string		HTML table
	 */
	function drawURLs_cfgSelectors()	{

			// depth
		$cell[] = $this->selectorBox(
			array(
				0 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_0'),
				1 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_1'),
				2 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_2'),
				3 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_3'),
				4 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_4'),
				99 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.depth_infi'),
			),
			'SET[depth]',
			$this->pObj->MOD_SETTINGS['depth'],
			0
		);
		$availableConfigurations = $this->crawlerObj->getConfigurationsForBranch($this->pObj->id, $this->pObj->MOD_SETTINGS['depth']?$this->pObj->MOD_SETTINGS['depth']:0 );

			// Configurations
		$cell[] = $this->selectorBox(
			array_combine($availableConfigurations, $availableConfigurations),
			'configurationSelection',
			$this->incomingConfigurationSelection,
			1
		);

			// Scheduled time:
		$cell[] = $this->selectorBox(
			array(
				'now' => $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.time.now'),
				'midnight' => $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.time.midnight'),
				'04:00' => $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.time.4am'),
			),
			'tstamp',
			t3lib_div::_POST('tstamp'),
			0
		);

		// TODO: check relevance
		/*
			// Requests per minute:
		$cell[] = $this->selectorBox(
			array(
				30 => '[Default]',
				1 => '1',
				5 => '5',
				10 => '10',
				20 => '20',
				30 => '30',
				50 => '50',
				100 => '100',
				200 => '200',
				1000 => '1000',
			),
			'SET[perminute]',
			$this->pObj->MOD_SETTINGS['perminute'],
			0
		);
		*/

		$output = '
			<table class="lrPadding c-list">
				<tr class="bgColor5 tableheader">
					<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.depth').':</td>
					<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.configurations').':</td>
					<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.scheduled').':</td>
				</tr>
				<tr class="bgColor4">
					<td valign="top">' . implode('</td>
					<td valign="top">', $cell).'</td>
				</tr>
			</table>';

		return $output;
	}

	/**
	 * Create Table header row for URL display
	 *
	 * @return	string		Table header
	 */
	function drawURLs_printTableHeader()	{

		$content = '
			<tr class="bgColor5 tableheader">
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.pagetitle').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.key').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.parametercfg').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.values').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.urls').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.options').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.parameters').':</td>
			</tr>';

		return $content;
	}












	/*******************************
	 *
	 * Shows log of indexed URLs
	 *
	 ******************************/

	/**
	 * Shows the log of indexed URLs
	 *
	 * @return	string		HTML output
	 */
	function drawLog()	{
		global $BACK_PATH;

			// Init:
		$this->crawlerObj = t3lib_div::makeInstance('tx_crawler_lib');
		$this->crawlerObj->setAccessMode('gui');
		$this->crawlerObj->setID = t3lib_div::md5int(microtime());

			// Read URL:
		if (t3lib_div::_GP('qid_read'))	{
			$this->crawlerObj->readUrl(t3lib_div::_GP('qid_read'),TRUE);
		}

			// Look for set ID sent - if it is, we will display contents of that set:
		$showSetId = t3lib_div::_GP('setID');

			// Show details:
		if (t3lib_div::_GP('qid_details'))	{

				// Get entry record:
			list($q_entry) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*','tx_crawler_queue','qid='.intval(t3lib_div::_GP('qid_details')));

				// Explode values:
			$q_entry['parameters'] = unserialize($q_entry['parameters']);
			$q_entry['result_data'] = unserialize($q_entry['result_data']);
			if (is_array($q_entry['result_data']))	{
				$q_entry['result_data']['content'] = unserialize($q_entry['result_data']['content']);
			}

				// Print rudimentary details:
			$output .= '
				<br /><br />
				<input type="submit" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.back').'" name="_back" />
				<input type="hidden" value="'.$this->pObj->id.'" name="id" />
				<input type="hidden" value="'.$showSetId.'" name="setID" />
				<br />
				Current server time: '.date('H:i:s',time()).
				t3lib_div::view_array($q_entry);
		} else {	// Show list:

				// If either id or set id, show list:
			if ($this->pObj->id || $showSetId)	{
				if ($this->pObj->id)	{
						// Drawing tree:
					$tree = t3lib_div::makeInstance('t3lib_pageTree');
					$perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
					$tree->init('AND '.$perms_clause);

						// Set root row:
					$HTML = '<img src="'.$BACK_PATH.t3lib_iconWorks::getIcon('pages',$this->pObj->pageinfo).'" width="18" height="16" align="top" class="c-recIcon" alt="" />';
					$tree->tree[] = Array(
						'row' => $this->pObj->pageinfo,
						'HTML' => $HTML
					);

						// Get branch beneath:
					if ($this->pObj->MOD_SETTINGS['depth'])	{
						$tree->getTree($this->pObj->id, $this->pObj->MOD_SETTINGS['depth'], '');
					}

						// Traverse page tree:
					$code = '';
					foreach($tree->tree as $data)	{
						$code.= $this->drawLog_addRows(
									$data['row'],
									$data['HTML'].t3lib_BEfunc::getRecordTitle('pages',$data['row'],TRUE)
								);
					}
				} else {
					$code = '';
					$code.= $this->drawLog_addRows(
								$showSetId,
								'Set ID: '.$showSetId
							);
				}

				$output = '';
				if ($code)	{

					$output .= '
						<br /><br />
						<input type="submit" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.reloadlist').'" name="_reload" />
						<input type="submit" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.downloadcsv').'" name="_csv" />
						<input type="submit" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.flushvisiblequeue').'" name="_flush" onclick="return confirm(\''.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.confirmyouresure').'\');" />
						<input type="submit" value="'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.flushfullqueue').'" name="_flush_all" onclick="return confirm(\''.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.confirmyouresure').'\');" />
						<input type="hidden" value="'.$this->pObj->id.'" name="id" />
						<input type="hidden" value="'.$showSetId.'" name="setID" />
						<br />
						'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.curtime').': '.date('H:i:s',time()).'
						<br /><br />


						<table class="lrPadding c-list crawlerlog">'.
							$this->drawLog_printTableHeader().
							$code.
						'</table>';
				}
			} else {	// Otherwise show available sets:
				$setList = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
								'set_id, count(*) as count_value, scheduled',
								'tx_crawler_queue',
								'',
								'set_id, scheduled',
								'scheduled DESC'
							);

				$code = '
					<tr class="bgColor5 tableheader">
						<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.setid').':</td>
						<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.count').'t:</td>
						<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.time').':</td>
					</tr>
				';

				$cc=0;
				foreach($setList as $set)	{
					$code.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							<td><a href="'.htmlspecialchars('index.php?setID='.$set['set_id']).'">'.$set['set_id'].'</a></td>
							<td>'.$set['count_value'].'</td>
							<td>'.t3lib_BEfunc::dateTimeAge($set['scheduled']).'</td>
						</tr>
					';

					$cc++;
				}

				$output .= '
					<br /><br />
					<table class="lrPadding c-list">'.
						$code.
					'</table>';
			}
		}

			// Output to CSV file:
		if (t3lib_div::_POST('_csv'))	{

			$csvLines = array();

				// Field names:
			reset($this->CSVaccu);
			$fieldNames = array_keys(current($this->CSVaccu));
			$csvLines[] = t3lib_div::csvValues($fieldNames);

				// Data:
			foreach($this->CSVaccu as $row)	{
				$csvLines[] = t3lib_div::csvValues($row);
			}

				// Creating output header:
			$mimeType = 'application/octet-stream';
			Header('Content-Type: '.$mimeType);
			Header('Content-Disposition: attachment; filename=CrawlerLog.csv');

				// Printing the content of the CSV lines:
			echo implode(chr(13).chr(10),$csvLines);

				// Exits:
			exit;
		}

			// Return output
		return 	$output;
	}

	/**
	 * Create the rows for display of the page tree
	 * For each page a number of rows are shown displaying GET variable configuration
	 *
	 * @param	array		Page row or set-id
	 * @param	string		Title string
	 * @return	string		HTML <tr> content (one or more)
	 */
	function drawLog_addRows($pageRow_setId, $titleString)	{

			// If Flush button is pressed, flush tables instead of selecting entries:

		if(t3lib_div::_POST('_flush')) {
			$doFlush = true;
			$doFullFlush = false;
		} elseif(t3lib_div::_POST('_flush_all')) {
			$doFlush = true;
			$doFullFlush = true;
		} else {
			$doFlush = false;
			$doFullFlush = false;
		}

			// Get result:
		if (is_array($pageRow_setId))	{
			$res = $this->crawlerObj->getLogEntriesForPageId($pageRow_setId['uid'], $this->pObj->MOD_SETTINGS['log_display'], $doFlush, $doFullFlush);
		} else {
			$res = $this->crawlerObj->getLogEntriesForSetId($pageRow_setId, $this->pObj->MOD_SETTINGS['log_display'], $doFlush, $doFullFlush);
		}

			// Init var:
		$colSpan = 9
				+ ($this->pObj->MOD_SETTINGS['log_resultLog'] ? -1 : 0)
				+ ($this->pObj->MOD_SETTINGS['log_feVars'] ? 3 : 0);

		if (count($res))	{
				// Traverse parameter combinations:
			$c = 0;
			$content='';
			foreach($res as $kk => $vv)	{

					// Title column:
				if (!$c)	{
					$titleClm = '<td rowspan="'.count($res).'">'.$titleString.'</td>';
				} else {
					$titleClm = '';
				}

					// Result:
				$resLog = '';
				if ($vv['result_data'])	{
					$requestContent = unserialize($vv['result_data']);
					$requestResult = unserialize($requestContent['content']);
					if (is_array($requestResult)) {
						if (empty($requestResult['errorlog'])) {
							$resStatus = 'OK';
						} else {
							$resStatus = implode("\n", $requestResult['errorlog']);
						}
						$resLog = is_array($requestResult['log']) ?  implode(chr(10),$requestResult['log']) : '';
					} else {
						$resStatus = 'Error: '.substr(ereg_replace('[[:space:]]+',' ',strip_tags($requestContent['content'])),0,10000).'...';
					}
				} else {
					$resStatus = '-';
				}

					// Compile row:
				$parameters = unserialize($vv['parameters']);

					// Put data into array:
				$rowData = array();
				if ($this->pObj->MOD_SETTINGS['log_resultLog'])	{
					$rowData['result_log'] = $resLog;
				} else {
					$rowData['scheduled'] = ($vv['scheduled']> 0) ? t3lib_BEfunc::datetime($vv['scheduled']) : ' '.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.immediate');
					$rowData['exec_time'] = $vv['exec_time'] ? t3lib_BEfunc::datetime($vv['exec_time']) : '-';
				}
				$rowData['result_status'] = $resStatus;
				$rowData['url'] = '<a href="'.htmlspecialchars($parameters['url']).'" target="_newWIndow">'.htmlspecialchars($parameters['url']).'</a>';
				$rowData['feUserGroupList'] = $parameters['feUserGroupList'];
				$rowData['procInstructions'] = is_array($parameters['procInstructions']) ? implode('; ',$parameters['procInstructions']) : '';
				$rowData['set_id'] = $vv['set_id'];

				if ($this->pObj->MOD_SETTINGS['log_feVars'])	{
					$rowData['tsfe_id'] = $requestResult['vars']['id'];
					$rowData['tsfe_gr_list'] = $requestResult['vars']['gr_list'];
					$rowData['tsfe_no_cache'] = $requestResult['vars']['no_cache'];
				}

					// Put rows together:
				$content.= '
					<tr class="bgColor'.($c%2 ? '-20':'-10').'">
						'.$titleClm.'
						<td><a href="index.php?id='.$this->pObj->id.'&qid_details='.$vv['qid'].'&setID='.t3lib_div::_GP('setID').'">'.htmlspecialchars($vv['qid']).'</a></td>
						<td><a href="index.php?id='.$this->pObj->id.'&qid_read='.$vv['qid'].'&setID='.t3lib_div::_GP('setID').'"><img src="'.$GLOBALS['BACK_PATH'].'gfx/refresh_n.gif" width="14" hspace="1" vspace="2" height="14" border="0" title="'.htmlspecialchars('Read').'" alt="" /></a></td>';
				foreach($rowData as $fKey => $value) {

					if (t3lib_div::inList('url',$fKey))	{
						$content.= '
						<td>'.$value.'</td>';
					} else {
						$content.= '
						<td>'.nl2br(htmlspecialchars($value)).'</td>';
					}
				}
				$content.= '
					</tr>';
				$c++;

				if ($doCSV)	{
						// Only for CSV (adding qid and scheduled/exec_time if needed):
					$rowData['result_log'] = implode('// ',explode(chr(10),$resLog));
					$rowData['qid'] = $vv['qid'];
					$rowData['scheduled'] = t3lib_BEfunc::datetime($vv['scheduled']);
					$rowData['exec_time'] = $vv['exec_time'] ? t3lib_BEfunc::datetime($vv['exec_time']) : '-';
					$this->CSVaccu[] = $rowData;
				}
			}
		} else {

				// Compile row:
			$content.= '
				<tr class="bgColor-20">
					<td>'.$titleString.'</td>
					<td colspan="'.$colSpan.'"><em>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.noentries').'</em></td>
				</tr>';
		}

		return $content;
	}

	/**
	 * Create Table header row (log)
	 *
	 * @return	string		Table header
	 */
	function drawLog_printTableHeader()	{

		$content = '
			<tr class="bgColor5 tableheader">
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.pagetitle').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.qid').':</td>
				<td>&nbsp;</td>'.
				($this->pObj->MOD_SETTINGS['log_resultLog'] ? '
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.resultlog').':</td>' : '
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.scheduledtime').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.runtime').':</td>').'
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.status').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.url').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.groups').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.procinstr').':</td>
				<td>'.$GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.setid').':</td>'.
				($this->pObj->MOD_SETTINGS['log_feVars'] ? '
				<td>'.htmlspecialchars('TSFE->id').'</td>
				<td>'.htmlspecialchars('TSFE->gr_list').'</td>
				<td>'.htmlspecialchars('TSFE->no_cache').'</td>' : '').'
			</tr>';

		return $content;
	}













	/*****************************
	 *
	 * CLI status display
	 *
	 *****************************/

	/**
	 * This method is used to show an overview about the active an the finished crawling processes
	 *
	 * @author Timo Schmidt
	 * @param void
	 * @return void
	 */
	protected function drawProcessOverviewAction(){

		global $BACK_PATH;

		$crawler = $this->findCrawler();
		$message = $this->handleProcessOverviewActions();

		$offset 	= intval(t3lib_div::_GP('offset'));
		$perpage 	= 20;

		$processRepository	= new tx_crawler_domain_process_repository();
		$queueRepository	= new tx_crawler_domain_queue_repository();

		$mode = $this->pObj->MOD_SETTINGS['processListMode'];
		if ($mode == 'detail') {
			$where = '';
		} elseif($mode == 'simple') {
			$where = 'active = 1';
		}

		$allProcesses 		= $processRepository->findAll('ttl','DESC', $perpage, $offset,$where);
		$allCount			= $processRepository->countAll($where);

		$listView			= new tx_crawler_view_process_list();
		$listView->setPageId($this->pObj->id);
		$listView->setIconPath($BACK_PATH.'../typo3conf/ext/crawler/template/process/res/img/');
		$listView->setProcessCollection($allProcesses);
		$listView->setCliPath($this->getCrawlerCliPath());
		$listView->setIsCrawlerEnabled(!$crawler->getDisabled());
		$listView->setTotalUnprocessedItemCount($queueRepository->countAllPendingItems());
		$listView->setAssignedUnprocessedItemCount($queueRepository->countAllAssignedPendingItems());
		$listView->setActiveProcessCount($processRepository->countActive());
		$listView->setMaxActiveProcessCount($this->extensionSettings['processLimit']);
		$listView->setActionMessage($message);
		$listView->setMode($mode);

		$paginationView		= new tx_crawler_view_pagination();
		$paginationView->setCurrentOffset($offset);
		$paginationView->setPerPage($perpage);
		$paginationView->setTotalItemCount($allCount);

		$output = $listView->render();

		if ($paginationView->getTotalPagesCount() > 1) {
			$output .= ' <br />'.$paginationView->render();
		}

		return $output;
	}

	/**
	 * Method to handle incomming actions of the process overview
	 *
	 * @param void
	 * @return void
	 */
	protected function handleProcessOverviewActions(){

		$crawler = $this->findCrawler();

		switch (t3lib_div::_GP('action')) {
			case 'stopCrawling' :
				//set the cli status to disable (all processes will be terminated)
				$crawler->setDisabled(true);
				break;
			case 'resumeCrawling' :
				//set the cli status to end (all processes will be terminated)
				$crawler->setDisabled(false);
				break;
			case 'addProcess' :
				$completePath = 'nohup ' . escapeshellcmd($this->getCrawlerCliPath()) . ' &';
				$handle = popen($completePath,'r');
				if ($handle === false) {
					throw new Exception($GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.newprocesserror'));
				}
				return $GLOBALS['LANG']->sL('LLL:EXT:crawler/modfunc1/locallang.xml:labels.newprocess');
				break;
		}
	}


	/**
	 * Returns the path to start the crawler from the command line
	 *
	 * @return string
	 */
	protected function getCrawlerCliPath(){
		$phpPath 		= $this->crawlerObj->extensionSettings['phpPath'] . ' ';
		$pathToTypo3 	= t3lib_div::getIndpEnv('TYPO3_DOCUMENT_ROOT');
		$cliPart	 	= '/typo3/cli_dispatch.phpsh crawler';
		return $phpPath.$pathToTypo3.$cliPart;
	}

	/**
	 * Returns the singleton instance of the crawler.
	 *
	 * @param void
	 * @return tx_crawler_lib crawler object
	 * @author Timo Schmidt <schmidt@aoemedia.de>
	 */
	protected function findCrawler(){
		if(!$this->crawlerObj instanceof tx_crawler_lib){
			$this->crawlerObj = t3lib_div::makeInstance('tx_crawler_lib');
		}
		return $this->crawlerObj;
	}



	/*****************************
	 *
	 * General Helper Functions
	 *
	 *****************************/

	/**
	 * Create selector box
	 *
	 * @param	array		Options key(value) => label pairs
	 * @param	string		Selector box name
	 * @param	string		Selector box value (array for multiple...)
	 * @param	boolean		If set, will draw multiple box.
	 * @return	string		HTML select element
	 */
	function selectorBox($optArray, $name, $value, $multiple)	{

		$options = array();
		foreach($optArray as $key => $val)	{
			$options[] = '
				<option value="'.htmlspecialchars($key).'"'.((!$multiple && !strcmp($value,$key)) || ($multiple && in_array($key,(array)$value))?' selected="selected"':'').'>'.htmlspecialchars($val).'</option>';
		}

		$output = '<select name="'.htmlspecialchars($name.($multiple?'[]':'')).'"'.($multiple ? ' multiple="multiple" size="'.count($options).'"' : '').'>'.implode('',$options).'</select>';

		return $output;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/crawler/modfunc1/class.tx_crawler_modfunc1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/crawler/modfunc1/class.tx_crawler_modfunc1.php']);
}

?>