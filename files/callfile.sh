#!/bin/bash
# path file /var/lib/asterisk/agi-bin/callfile.sh
fromNumber=$1
toNumber=$2

if [ "${fromNumber}" == "" -o "${toNumber}" == "" ]; then
  echo "error"
  exit
fi
sleep 10
/bin/echo "Channel: Local/${fromNumber}@amocrm
MaxRetries: 1
RetryTime: 15
WaitTime: 30
Context: amocrm-out-second
Callerid: amocrm <${toNumber}>
Extension: ${toNumber}
Priority: 1
" > /tmp/test.call

chown asterisk:asterisk /tmp/test.call
mv /tmp/test.call /var/spool/asterisk/outgoing