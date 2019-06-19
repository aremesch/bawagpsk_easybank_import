<?php
// Download BAWAGPSK and Easybank Transactions and import into database
// The Android App API is used (inofficially) for downloading this data
// For details see: https://blog.haschek.at/2018/reverse-engineering-your-mobile-banking-app.html
/*
Data structure for insert of transactions (mariadb/mysql):

DROP TABLE `ImportTransactions`;
CREATE TABLE `ImportTransactions` (
`ID` INT NOT NULL AUTO_INCREMENT,
`AccountNum` VARCHAR(35) NOT NULL,
`StatementNumber` VARCHAR(20) NOT NULL,
`Position` VARCHAR(20) NOT NULL,
`ForeignId` INT NULL DEFAULT NULL,
`Period` VARCHAR(255) NULL DEFAULT NULL,
`CardNumber` DECIMAL(2) NULL DEFAULT NULL,
`TransactionDate` DATETIME NOT NULL,
`Description` VARCHAR(255) NULL DEFAULT NULL,
`Place` VARCHAR(255) NULL DEFAULT NULL,
`OriginalCurrency` VARCHAR(10) NULL DEFAULT NULL,
`OriginalAmount` DOUBLE NULL DEFAULT NULL,
`TotalAmount` DOUBLE NULL DEFAULT NULL,
`ExchangeRate` DOUBLE NULL DEFAULT NULL,
`TransactionAmount` DOUBLE NOT NULL,
`ProcessingFee` DOUBLE NULL DEFAULT NULL,
`CashWithdrawalFee` DOUBLE NULL DEFAULT NULL,
`ClearingDate` DATETIME NULL DEFAULT NULL,
`ImportDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`ID`)
)
*/

include_once('easybank.class.php');
include_once('credentials.db.php');
include_once('credentials.php');

$conn = new mysqli($dbhost, $dbuser, $dbpassword, $dbname);
/* check connection */
if ($conn->connect_error) {
    die("Connect failed: ".$conn->connect_error);
}

foreach($credentials as $c) {
	$e = new EasyBank($c['id'],$c['pin']);
	$e->setInstitute($c['institute']);
	$e->logIn();
	$accounts = $e->getAccounts();
	$i = 0;
	foreach($accounts as $a) {
		if ($a['ProductId']['ProductType'] == "BUILDING_SAVINGS")
			continue;
		$buchungen = $e->getStatements($i);
		if ($buchungen == false) continue;
		printf("Processing %d bookings %s - %s ",count($buchungen),$a['ProductId']['ProductDescription'],$a['ProductId']['iBAN']);
/*		if ($a['ProductId']['ProductDescription'] == "easy premium") {
			var_dump($buchungen);
			exit;	
		}*/
		// get max id to be able to skip records already imported
		$result = $conn->query("SELECT MAX(ForeignId) AS maxid, MAX(ClearingDate) AS maxdate FROM ImportTransactions WHERE AccountNum = '".$conn->real_escape_string($a['ProductId']['iBAN'])."'");
		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$maxid = $row['maxid'];
			$maxdate = strtotime($row['maxdate']);
		} else {
			$maxid = 0;
			$maxdate = strtotime('2000-01-01');
		}

		$rows_inserted = 0;
		$rows_failed = 0;
		foreach($buchungen as $b) {
			if (isset($b['OperationNumber'])) {
				if ($b['OperationNumber'] <= $maxid) continue;
				$sql = "INSERT INTO ImportTransactions (AccountNum,StatementNumber,Position,ForeignId,TotalAmount,OriginalAmount,OriginalCurrency,TransactionDate,ClearingDate,Description) VALUES (".
					"'".$conn->real_escape_string($a['ProductId']['iBAN'])."',".
					"'".$conn->real_escape_string($b['StatementNumber'])."',".
					"'".$conn->real_escape_string($b['Position'])."',".
					$conn->real_escape_string($b['OperationNumber']).",".
					$conn->real_escape_string($b['Amount']['Amount']).",".
					$conn->real_escape_string($b['Amount']['Amount']).",".
					"'".$conn->real_escape_string($b['Amount']['Currency'])."',".
					"'".$conn->real_escape_string(substr($b['ValueDate'],0,strpos($b['ValueDate'],'+')))."',".
					"'".$conn->real_escape_string(substr($b['BookingDate'],0,strpos($b['BookingDate'],'+')))."',".
					"'".$conn->real_escape_string(implode($b['TextLines']['Text'],' '))."')";
			} else {
				if ($maxdate >= strtotime(substr($b['BookingDate'],0,strpos($b['BookingDate'],'+')))) continue;
				$sql = "INSERT INTO ImportTransactions (AccountNum,StatementNumber,Position,TotalAmount,OriginalAmount,OriginalCurrency,TransactionDate,ClearingDate,Description) VALUES (".
					"'".$conn->real_escape_string($a['ProductId']['iBAN'])."',".
					"'".$conn->real_escape_string($b['StatementNumber'])."',".
					"'".$conn->real_escape_string($b['Position'])."',".
					$conn->real_escape_string($b['Amount']['Amount']).",".
					$conn->real_escape_string($b['Amount']['Amount']).",".
					"'".$conn->real_escape_string($b['Amount']['Currency'])."',".
					"'".$conn->real_escape_string(substr($b['ValueDate'],0,strpos($b['ValueDate'],'+')))."',".
					"'".$conn->real_escape_string(substr($b['BookingDate'],0,strpos($b['BookingDate'],'+')))."',".
					"'".$conn->real_escape_string(implode($b['TextLines']['Text'],' '))."')";
			}

			if ($conn->query($sql)) {
				$rows_inserted = $rows_inserted + $conn->affected_rows;
			} else {
				$rows_failed = $rows_failed + $conn->affected_rows;
				printf("Failed to insert row - %s\n", $sql);
			}
		}
		printf("inserted %d rows, %d rows failed\n", $rows_inserted, $rows_failed);
		$i++;
	}
}

$conn->close();
?>

