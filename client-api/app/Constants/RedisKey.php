<?php

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;

class RedisKey extends AbstractConstants
{
    //NFT爬虫
    const NFT_REPTILE = 'reptask:nft_reptile';

    //product_attributes
    const PRODUCT_ATTRIBUTE = 'product_attribute:';
    const COLLECTION_ATTRIBUTE = 'collection_attribute:';

    //nft数据重试
    const NFT_REPTILE_RETRY = 'reptask:nft_reptile_retry';

    //静态资源处理
    const NFT_REPTILE_PIC_HANDLE = 'reptask:nft_reptile_pic_handle';

    //根据用户地址获得所有NFT资产
    const FETCH_NFT_BY_ADDRESS = 'reptask:fetch_nft_by_address';
}