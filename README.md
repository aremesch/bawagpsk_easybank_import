# bawagpsk_easybank_import

*UPDATE 2019-10-19: BAWAG/easybank changed their App, so the interfaces used in this project stopped working on Sep 12th, 2019.*

Import transactions from bawagpsk.com and easybank.at accounts into a mysql database.

Based on https://gist.github.com/chrisiaut/f79c6453069f938eb0ebd9825c92e483 and the very interesting explanation given here: https://blog.haschek.at/2018/reverse-engineering-your-mobile-banking-app.html

Setup:
Clone repository, fill in your database & ebanking credentials, create database and add table as given in file bawag-dl.php, then you should be good to go.
