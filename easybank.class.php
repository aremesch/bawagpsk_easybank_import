<?php 

/*
* EasyBank.at client lib
* Reverse engineered by looking at the API calls of the app
*
* Example usage:
*
* <?php
* include_once('easybank.class.php');
* $e = new EasyBank(<your onlinebanking id>,<your onlinebanking password>);
* $e->logIn();
* $buchungen = $e->getStatements();
* foreach($buchungen as $b)
* {
*     $type = ($b['Amount']['Amount']>0?'Erhalten':'Gezahlt');
*     $sym = ($b['Amount']['Amount']>0?'+':'-');
*     echo "[$sym] Buchung vom ".$b['BookingDate']."\t".abs($b['Amount']['Amount'])." EUR $type\tText: ".implode($b['TextLines']['Text'],' ')."\n";
* }
* <?php
*
* Returns something like:
*[+] Buchung vom 2018-04-03+02:00        XXXX EUR Erhalten       Text: Max Mustermann
*[-] Buchung vom 2018-04-03+02:00        XXXX EUR Gezahlt        Text: BG/XXXX Entgelt für Kontoführung
*[+] Buchung vom 2018-04-03+02:00        XXXX EUR Erhalten       Text: Zinsen HABEN BG/XXXX
*
*/

class EasyBank
{
    private $url = 'https://ebanking.easybank.at/ebanking.mobile/SelfServiceMobileService';
    private $iban,$disposer,$pin,$accounts,$logindata,$session;

    function __construct($disposer,$pin)
    {
        $this->disposer = $disposer;
        $this->pin = $pin;
    }

    function getStatements($account=false)
    {
      if(!$account)
        $account = $this->accounts[0];

      $answer = $this->makeRequest('<v:Envelope xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:d="http://www.w3.org/2001/XMLSchema" xmlns:c="http://schemas.xmlsoap.org/soap/encoding/" xmlns:v="http://schemas.xmlsoap.org/soap/envelope/">
        <v:Header />
        <v:Body>
          <n0:GetAccountStatementItemsRequest id="o0" c:root="1" xmlns:n0="urn:selfservicemobile.bawag.com/ws/v0100-draft03">
            <Context>
              <Channel>MOBILE</Channel>
              <DevID>no</DevID>
              <DeviceIdentifier>1337</DeviceIdentifier>
              <Language>DE</Language>
            </Context>
            <ProductId>
              <AccountNumber>'.$account['AccountNumber'].'</AccountNumber>
              <AccountOwner>'.$account['ProductId']['AccountOwner'].'</AccountOwner>
              <FinancialInstitute>
                <BIC>EASYATW1</BIC>
                <BankCode>14200</BankCode>
                <Code>0109</Code>
                <ShortName>EASYBANK</ShortName>
              </FinancialInstitute>
              <iBAN>'.$account['ProductId']['iBAN'].'</iBAN>
              <ProductCode>'.$account['ProductCode'].'</ProductCode>
              <ProductDescription>'.$account['ProductId']['ProductDescription'].'</ProductDescription>
              <ProductType>'.$account['ProductId']['ProductType'].'</ProductType>
            </ProductId>
            <ServerSessionID>'.$this->session.'</ServerSessionID>
            <StatementSearchCriteria>
              <MinAmount>0</MinAmount>
              <MinDatePosted>'.(date("Y")-1).'-01-01+01:00</MinDatePosted>
              <SortingColumn>BOOKING_DATE</SortingColumn>
              <TransactionType>ALL</TransactionType>
            </StatementSearchCriteria>
          </n0:GetAccountStatementItemsRequest>
        </v:Body>
      </v:Envelope>');


      if($answer['OK'])
        return $answer['OK']['AccountStatementItem'];
      return false;
    }

    function logIn()
    {
        $answer = $this->makeRequest('<v:Envelope xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:d="http://www.w3.org/2001/XMLSchema" xmlns:c="http://schemas.xmlsoap.org/soap/encoding/" xmlns:v="http://schemas.xmlsoap.org/soap/envelope/">
        <v:Header />
        <v:Body>
          <n0:LoginRequest id="o0" c:root="1" xmlns:n0="urn:selfservicemobile.bawag.com/ws/v0100-draft03">
            <Authentication>
              <Pin>'.$this->pin.'</Pin>
            </Authentication>
            <Context>
              <Channel>MOBILE</Channel>
              <DevID>no</DevID>
              <DeviceIdentifier>1337</DeviceIdentifier>
              <Language>DE</Language>
            </Context>
            <DisposerContext>
              <DisposerNumber>'.str_pad($this->disposer, 17,'0',STR_PAD_LEFT).'</DisposerNumber>
              <FinancialInstitute>
                <BankCode>14200</BankCode>
                <ShortName>EASYBANK</ShortName>
              </FinancialInstitute>
            </DisposerContext>
            <isIncludeLoginImage>false</isIncludeLoginImage>
          </n0:LoginRequest>
        </v:Body>
      </v:Envelope>');

      $this->logindata = $answer;

      if($answer['OK'])
      {
        $this->session = $answer['OK']["ServerSessionID"];
        foreach($answer['OK']['Disposer']['Products'] as $product)
        {
          $this->accounts[] = $product;
        }
      }

      return $answer;
    }

    function makeRequest($xml)
    {
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: urn:selfservicemobile.bawag.com/ws/v0100-draft03/login", 
            "Content-length: ".strlen($xml),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dalvik/2.1.0_(Linux;_U;_Android_8.0.0;_ONEPLUS_A3003_Build/OPR6.170623.013) mobileBanking/easybank_5.1 target/PROD platform/phone_6.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // converting
        $response = curl_exec($ch); 
        curl_close($ch);

        $start = strpos($response,'<env:Body>') + strlen('<env:Body>');
        $end = strpos($response,'</env:Body>');
        $xml = substr($response,$start, ($end-$start) );

        return json_decode(json_encode(simplexml_load_string($xml)), true);
    }
}