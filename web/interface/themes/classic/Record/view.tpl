{js filename="ajax_common.js"}
{js filename="record.js"}
{if isset($syndetics_plus_js)}
<script src="{$syndetics_plus_js}" type="text/javascript"></script>
{/if}
{if !empty($addThis)}
<script type="text/javascript" src="https://s7.addthis.com/js/250/addthis_widget.js?pub={$addThis|escape:"url"}"></script>
{/if}

<div id="bd">
  <div id="yui-main" class="content">
    <div class="yui-b first contentbox">
      <div class="yui-ge">

        <div class="record">
          {if $lastsearch}
            <a href="{$lastsearch|escape}#record{$id|escape:"url"}" class="backtosearch">&laquo; {translate text="Back to Search Results"}</a>
          {/if}

          <ul class="tools">
            <li><a href="{$url}/Record/{$id|escape:"url"}/Cite" class="cite" onClick="getLightbox('Record', 'Cite', '{$id|escape}', null, '{translate text="Cite this"}'); return false;">{translate text="Cite this"}</a></li>
            <li><a href="{$url}/Record/{$id|escape:"url"}/SMS" class="sms" onClick="getLightbox('Record', 'SMS', '{$id|escape}', null, '{translate text="Text this"}'); return false;">{translate text="Text this"}</a></li>
            <li><a href="{$url}/Record/{$id|escape:"url"}/Email" class="mail" onClick="getLightbox('Record', 'Email', '{$id|escape}', null, '{translate text="Email this"}'); return false;">{translate text="Email this"}</a></li>
            {if is_array($exportFormats) && count($exportFormats) > 0}
              <li>
                <a href="{$url}/Record/{$id|escape:"url"}/Export?style={$exportFormats.0|escape:"url"}" class="export" onClick="toggleMenu('exportMenu'); return false;">{translate text="Export Record"}</a><br />
                <ul class="menu" id="exportMenu">
                  {foreach from=$exportFormats item=exportFormat}
                    <li><a {if $exportFormat=="RefWorks"}target="{$exportFormat}Main" {/if}href="{$url}/Record/{$id|escape:"url"}/Export?style={$exportFormat|escape:"url"}">{translate text="Export to"} {$exportFormat|escape}</a></li>
                  {/foreach}
                </ul>
              </li>
            {/if}
            <li id="saveLink"><a href="{$url}/Record/{$id|escape:"url"}/Save" class="fav" onClick="getLightbox('Record', 'Save', '{$id|escape}', null, '{translate text="Add to favorites"}'); return false;">{translate text="Add to favorites"}</a></li>
            {if !empty($addThis)}
            <li id="addThis"><a class="addThis addthis_button"" href="https://www.addthis.com/bookmark.php?v=250&amp;pub={$addThis|escape:"url"}">{translate text='Bookmark'}</a></li>
            {/if}
          </ul>
          <script language="JavaScript" type="text/javascript">
              function redrawSaveStatus() {literal}{{/literal}
                  getSaveStatus('{$id|escape:"javascript"}', 'saveLink');
              {literal}}{/literal}
              {if $user}redrawSaveStatus();{/if}
          </script>

          <div style="clear: right;"></div>

          {if $errorMsg || $infoMsg}
          <div class="messages">
          {if $errorMsg}<div class="error">{$errorMsg|translate}</div>{/if}
          {if $infoMsg}<div class="userMsg">{$infoMsg|translate}</div>{/if}
          </div>
          {/if}

          {if $previousRecord || $nextRecord}
          <div class="resultscroller">
            {if $previousRecord}<a href="{$url}/Record/{$previousRecord}">&laquo; {translate text="Prev"}</a>{/if}
            #{$currentRecordPosition} {translate text='of'} {$resultTotal}
            {if $nextRecord}<a href="{$url}/Record/{$nextRecord}">{translate text="Next"} &raquo;</a>{/if}
          </div>
          {/if}

          {include file=$coreMetadata}

          <!-- Display Tab Navigation -->
          <div id="tabnav" style="clear: left;">
            <ul>
              {if $hasHoldings}
              <li{if $tab == 'Holdings' || $tab == 'Hold'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/Holdings#tabnav" class="first"><span></span>{translate text='Holdings'}</a>
              </li>
              {/if}
              <li{if $tab == 'Description'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/Description#tabnav" class="first"><span></span>{translate text='Description'}</a>
              </li>
              {if $hasTOC}
              <li{if $tab == 'TOC'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/TOC#tabnav" class="first"><span></span>{translate text='Table of Contents'}</a>
              </li>
              {/if}
              <li{if $tab == 'UserComments'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/UserComments#tabnav" class="first"><span></span>{translate text='Comments'}</a>
              </li>
              {if $hasReviews}
              <li{if $tab == 'Reviews'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/Reviews#tabnav" class="first"><span></span>{translate text='Reviews'}</a>
              </li>
              {/if}
              {if $hasExcerpt}
              <li{if $tab == 'Excerpt'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/Excerpt#tabnav" class="first"><span></span>{translate text='Excerpt'}</a>
              </li>
              {/if}
              {if $hasMap}
                <li{if $tab == 'Map'} class="active"{/if}>
                  <a href="{$url}/Record/{$id|escape:"url"}/Map#tabnav" class="first"><span></span>{translate text='Map View'}</a>
                </li>
              {/if}
              <li{if $tab == 'Details'} class="active"{/if}>
                <a href="{$url}/Record/{$id|escape:"url"}/Details#tabnav" class="first"><span></span>{translate text='Staff View'}</a>
              </li>
            </ul>
          </div>
          <div style="clear: left;"></div>

          <div class="details">
          {include file="Record/$subTemplate"}
          </div>

          <span class="Z3988" title="{$openURL|escape}"></span>

        </div>
      </div>
    </div>
      
  </div>
      
  <div class="yui-b">
  
    <div class="box submenu">
      <h4>{translate text="Similar Items"}</h4>
      {if is_array($similarRecords)}
      <ul class="similar">
        {foreach from=$similarRecords item=similar}
        <li>
          {if is_array($similar.format)}
            <span class="{$similar.format[0]|lower|regex_replace:"/[^a-z0-9]/":""}">
          {else}
            <span class="{$similar.format|lower|regex_replace:"/[^a-z0-9]/":""}">
          {/if}
          <a href="{$url}/Record/{$similar.id|escape:"url"}">{$similar.title|escape}</a>
          </span>
          <span style="font-size: .8em">
          {if $similar.author}<br>{translate text='By'}: {$similar.author|escape}{/if}
          {if $similar.publishDate}<br>{translate text='Published'}: ({$similar.publishDate.0|escape}){/if}
          </span>
        </li>
        {/foreach}
      </ul>
      {else}
      {translate text='Cannot find similar records'}
      {/if}
    </div>

    {if is_array($editions)}
    <div class="box submenu">
      <h4>{translate text="Other Editions"}</h4>
      <ul class="similar">
        {foreach from=$editions item=edition}
        <li>
          {if is_array($edition.format)}
            <span class="{$edition.format[0]|lower|regex_replace:"/[^a-z0-9]/":""}">
          {else}
            <span class="{$edition.format|lower|regex_replace:"/[^a-z0-9]/":""}">
          {/if}
          <a href="{$url}/Record/{$edition.id|escape:"url"}">{$edition.title|escape}</a>
          </span>
          {$edition.edition|escape}
          {if $edition.publishDate}({$edition.publishDate.0|escape}){/if}
        </li>
        {/foreach}
      </ul>
    </div>
    {/if}

  </div>
</div>
{if $showPreviews}
{if $showGBSPreviews} 
<script src="https://encrypted.google.com/books?jscmd=viewapi&amp;bibkeys={if $isbn}ISBN{$isbn}{/if}{if $holdingLCCN}{if $isbn},{/if}LCCN{$holdingLCCN}{/if}{if $holdingArrOCLC}{if $isbn || $holdingLCCN},{/if}{foreach from=$holdingArrOCLC item=holdingOCLC name=oclcLoop}OCLC{$holdingOCLC}{if !$smarty.foreach.oclcLoop.last},{/if}{/foreach}{/if}&amp;callback=ProcessGBSBookInfo" type="text/javascript"></script>
{/if}
{if $showOLPreviews}
<script src="http://openlibrary.org/api/books?bibkeys={if $isbn}ISBN{$isbn}{/if}{if $holdingLCCN}{if $isbn},{/if}LCCN{$holdingLCCN}{/if}{if $holdingArrOCLC}{if $isbn || $holdingLCCN},{/if}{foreach from=$holdingArrOCLC item=holdingOCLC name=oclcLoop}OCLC{$holdingOCLC}{if !$smarty.foreach.oclcLoop.last},{/if}{/foreach}{/if}&amp;callback=ProcessOLBookInfo" type="text/javascript"></script>
{/if}
{if $showHTPreviews}
<script src="http://catalog.hathitrust.org/api/volumes/brief/json/id:HT{$id|escape};{if $isbn}isbn:{$isbn}{/if}{if $holdingLCCN}{if $isbn};{/if}lccn:{$holdingLCCN}{/if}{if $holdingArrOCLC}{if $isbn || $holdingLCCN};{/if}{foreach from=$holdingArrOCLC item=holdingOCLC name=oclcLoop}oclc:{$holdingOCLC}{if !$smarty.foreach.oclcLoop.last};{/if}{/foreach}{/if}&amp;callback=ProcessHTBookInfo" type="text/javascript"></script>
{/if}
{/if}
