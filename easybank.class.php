<?php 

/*
* EasyBank.at/BAWAGPSK.com client lib
* Reverse engineered by looking at the API calls of the app
*/

class EasyBank
{
    private $url = 'https://ebanking.easybank.at/ebanking.mobile/SelfServiceMobileService';
    private $iban,$disposer,$pin,$accounts,$logindata,$session;
    private $bic,$bankcode,$bankshortname,$appshortname;

    function __construct($disposer,$pin)
    {
        $this->disposer = $disposer;
        $this->pin = $pin;
    }

    function getAccounts()
    {
        return $this->accounts;
    }

    function setInstitute($institute)
    {
      switch ($institute) {
      case "BAWAG":
      case "BAWAGPSK":
        $this->bic="BAWAATWW";
        $this->bankcode="14000";
        $this->bankshortname="BAWAG";
        $this->appshortname="bawag";
        break;
      case "EASYBANK":
        $this->bic="EASYATW1";
        $this->bankcode="14200";
        $this->bankshortname="EASYBANK";
        $this->appshortname="easybank";
        break;
      default:
        echo "Unknown institute $institute. Allowed values are BAWAG or EASYBANK";
        exit;
      }
    }

    function getStatements($accountid=0)
    {
      $account = $this->accounts[$accountid];

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
                <BIC>'.$this->bic.'</BIC>
                <BankCode>'.$this->bankcode.'</BankCode>
                <Code>0109</Code>
                <ShortName>'.$this->bankshortname.'</ShortName>
              </FinancialInstitute>
              <iBAN>'.$account['ProductId']['iBAN'].'</iBAN>
              <ProductCode>'.$account['ProductId']['ProductCode'].'</ProductCode>
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

      if($answer['OK']) {
        if(isset($answer['OK']['AccountStatementItem']['Position']))
          return [ $answer['OK']['AccountStatementItem'] ];
        else
          return $answer['OK']['AccountStatementItem'];
      }
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
                <BankCode>'.$this->bankcode.'</BankCode>
                <ShortName>'.$this->bankshortname.'</ShortName>
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
        foreach($answer['OK']['Disposer']['Products']['Product'] as $product)
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dalvik/2.1.0_(Linux;_U;_Android_8.0.0;_ONEPLUS_A3003_Build/OPR6.170623.013) mobileBanking/'.$this->appshortname.'_5.1 target/PROD platform/phone_6.0');
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
