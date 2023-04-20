<?php

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;

class Common extends AbstractConstants
{
    //测试以太网接口地址
    const TEST_ETH_API = 'https://api-rinkeby.etherscan.io/api';

    //正式以太网接口地址
    const PRODUCT_ETH_API = 'https://api.etherscan.io/api';

    const ETH_API_TOKEN = 'JJKF2MX14C4HWP9C8BN5CS964CK5Z5IC6D';

    //交易地址
    const TEST_ETH_TRADE_URL = 'https://rinkeby.etherscan.io/tx/';
    const PRODUCT_ETH_TRADE_URL = 'https://etherscan.io/tx/';

    const PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDG64rRoq45Ytz9
rSUWKc87BD861vjTJL9FppZWEjxlolA0tAdOENjvwILJBPBWIm54efeK8y+y65dt
gFbKMRkxRR5rb3wfZVvf8O3JK+R/m86jE6ZWXPDROgee1tWF9kq/m+C16rURTkPC
zQvSbs/7P5x7TEBQ4GoeSFFbyC7CaRU4ROxTu2JYd+xLQf9fVDDCROk7VTPI2Z9x
FV6mZRkv3TLfj341xpSHrQMl3dmsOCmdD3EloWH0Z7PZL1rjr1v9YKlh19cIZuLP
g/EmvjTqMOLItL+tsRefahdQuMHnEzyF8n3nD9KU/udKuDCOLzaTp+4uflx9aEVM
XiuzonAjAgMBAAECggEBALYboUT2aAYFakebId6+fAeNhc16TOYQOEOtlOhLXZu2
EzOMtTtU1SX42kLqEJTqhLQrBOLibAKjCEipO8tzU5r1qjm1IK8lfgzwZuDLHC9v
FqfZL2jVQWpqc9uI1oYDyr7MF9azfvzO594JFg+afzGHNNz0G9Vu/fenQUSDabt0
Flgcmi0ysslyWf5PP49TEXFruTAkarTj/udmJBSruDyQk4wtzZLTieCX6XTSuYpo
3qgWCLR7cE9vkSVkISMAJLuypqQJj4eeESo6Wpi0Incb08SrYKr56Fla2yuWJ+UF
3kBwFT2l3iQOvPeEiUZkeBfrjFh3u9vUIR2HlI+nI2ECgYEA8eT4ZrVxXsHTd4Lk
3DfhcWL2YuYTWJokxETDFnEMKxRS/oDnJQGaRs/XCh/wiCq5qr0F1eIsC6fY1sJT
8H8cLZKladTY/ROAbvTYq0Df8dqcffSDy5OnXWXToQcHtJ61MMt8IMh45rBAd5fd
0si6XtgcPqvnPT6OxyrQ0Z5wA4kCgYEA0oULvfVG7vZKdomrqgrkXpCZuGrbhqSR
3oMp4KqiYAWJZuTiul1pSlRnLAriYHGAGGqcq+bgY1FP0sSfKE/vZ78WwLZxQ3G2
8UwOqRrxtq+ijCHx1Us5KzAGYwIuydYjKrz8pD+TZeHFO7Xz680G3i6uyIFIymOa
mry5eGXBb0sCgYAWiXjDSQBpDbIAHofoJKSyhb/i8wC2bpYiWy2594pksR4SbDwc
7ItNMawdW2Bzr1dhGv9iMBJee6LuT2i2rYEYleMnexdEbP64V8OgIQk8ZVvTOGbX
HsinIQeYpykGoQrMowjLnSH2jFFVUybtrpn+oC/xft6qjBuNXuXZweM50QKBgAuf
MLDB87KJgj7dBs8SXt0hmnrl20yplSv1jcBLaz9lztoRVLr5ITDrS+7QgwLUAUBX
0mJjEowpFwEJvceZ8huGHxlwePxCMNlNW5nWVPXC6HRYA2PFDVjnA9M/cZnO6o4X
dNMUd2yudBcByn+ACBsH9Lo4+O3DZeuY85xD8dPVAoGBAKpYoagDucqDldqwWlJc
3r28ciOtyfkblIImhmsp1tAyCJvr2F45Qw5IRUFXgivYfJaY2yRDIuSbjKXV1hlZ
WNcLtljDh3Sx2wS2Nmm9LdBUNEK0dzHnKSYoUEEYskWYbQVNu7583h6+/qkw7R0Y
nNCWmjwj4766Bl4tHd+LWZar
-----END PRIVATE KEY-----";

    const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxuuK0aKuOWLc/a0lFinP
OwQ/Otb40yS/RaaWVhI8ZaJQNLQHThDY78CCyQTwViJueHn3ivMvsuuXbYBWyjEZ
MUUea298H2Vb3/DtySvkf5vOoxOmVlzw0ToHntbVhfZKv5vgteq1EU5Dws0L0m7P
+z+ce0xAUOBqHkhRW8guwmkVOETsU7tiWHfsS0H/X1QwwkTpO1UzyNmfcRVepmUZ
L90y349+NcaUh60DJd3ZrDgpnQ9xJaFh9Gez2S9a469b/WCpYdfXCGbiz4PxJr40
6jDiyLS/rbEXn2oXULjB5xM8hfJ95w/SlP7nSrgwji82k6fuLn5cfWhFTF4rs6Jw
IwIDAQAB
-----END PUBLIC KEY-----";
}