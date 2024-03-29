{js filename="record.js"}
{js filename="openurl.js"}
<div class="span-18{if $sidebarOnLeft} push-5 last{/if}">
  <div class="toolbar">
    <ul>
      {* TODO: citations <li><a href="{$url}/Summon/Cite?id={$id|escape:"url"}" class="citeRecord summonRecord cite" id="citeRecord{$id|escape}" title="{translate text="Cite this"}">{translate text="Cite this"}</a></li> *}
      <li><a href="{$url}/Summon/SMS?id={$id|escape:"url"}" class="smsRecord smsSummon sms" id="smsRecord{$id|escape}" title="{translate text="Text this"}">{translate text="Text this"}</a></li>
      <li><a href="{$url}/Summon/Email?id={$id|escape:"url"}" class="mailRecord mailSummon mail" id="mailRecord{$id|escape}" title="{translate text="Email this"}">{translate text="Email this"}</a></li>
      {* TODO: export 
      {if is_array($exportFormats) && count($exportFormats) > 0}
      <li>
        <a href="{$url}/Summon/Export?id={$id|escape:"url"}&amp;style={$exportFormats.0|escape:"url"}" class="export exportMenu">{translate text="Export Record"}</a>
        <ul class="menu offscreen" id="exportMenu">
        {foreach from=$exportFormats item=exportFormat}
          <li><a {if $exportFormat=="RefWorks"}target="{$exportFormat}Main" {/if}href="{$url}/Summon/Export?id={$id|escape:"url"}&amp;style={$exportFormat|escape:"url"}">{translate text="Export to"} {$exportFormat|escape}</a></li>
        {/foreach}
        </ul>
      </li>
      {/if}
      *}
      {* TODO: save
      <li id="saveLink"><a href="{$url}/Summon/Save?id={$id|escape:"url"}" class="saveRecord summonRecord fav" id="saveRecord{$id|escape}" title="{translate text="Add to favorites"}">{translate text="Add to favorites"}</a></li>
       *}
      {if !empty($addThis)}
      <li id="addThis"><a class="addThis addthis_button"" href="https://www.addthis.com/bookmark.php?v=250&amp;pub={$addThis|escape:"url"}">{translate text='Bookmark'}</a></li>
      {/if}
    </ul>
    <div class="clear"></div>
  </div>

  <div class="record recordId" id="record{$id|escape}">
    {* Display link to content -- if a URL is provided, only use it if no 
       OpenURL setting exists or if the OpenURL won't lead to full text --
       these URI values aren't always very useful, so they should be linked
       as a last resort only. *}
    <div class="button alignright">
    {if $record.link}
      <a href="{$record.link|escape}">{translate text='Get full text'}</a>
    {elseif $record.url && (!$openUrlBase || !$record.hasFullText)}
      {foreach from=$record.url.0 item="value"}
        <a href="{$value|escape}">{translate text='Get full text'}</a><br/>
      {/foreach}
    {elseif $openUrlBase}
      {include file="Search/openurl.tpl" openUrl=$record.openUrl}
    {/if}
    </div>

    <div class="alignright"><span class="{$record.ContentType.0|replace:" ":""|escape}">{$record.ContentType.0|escape}</span></div>

    {* Display Title *}
    <h1>{$record.Title.0|escape}</h1>
    {* End Title *}

    {* Display Cover Image *}
    <div class="alignleft">
      <img alt="{translate text='Cover Image'}" src="{$path}/bookcover.php?size=small{if $record.ISBN.0}&amp;isn={$record.ISBN.0|@formatISBN}{/if}{if $record.ContentType.0}&amp;contenttype={$record.ContentType.0|escape:"url"}{/if}"/>
    </div>
    {* End Cover Image *}
    
    {* Display Abstract/Snippet *}
    {if $record.Abstract}
      <p class="snippet">{$record.Abstract.0|escape}</p>
    {elseif $record.Snippet.0 != ""}
      <blockquote>
        <span class="quotestart">&#8220;</span>{$record.Snippet.0|escape}<span class="quoteend">&#8221;</span>
      </blockquote>
    {/if}

    {* Display Main Details *}
    <table cellpadding="2" cellspacing="0" border="0" class="citation">
    
      {if $record.Author}
      <tr valign="top">
        <th>{translate text='Author'}(s): </th>
        <td>
    {foreach from=$record.Author item="author" name="loop"}
    <a href="{$url}/Summon/Search?type=Author&amp;lookfor={$author|escape:"url"}">{$author|escape}</a>{if !$smarty.foreach.loop.last},{/if} 
    {/foreach}
        </td>
      </tr>
      {/if}

      {if $record.PublicationTitle}
      <tr valign="top">
        <th>{translate text='Publication'}: </th>
        <td>{$record.PublicationTitle.0|escape}</td>
      </tr>
      {/if}

      {assign var=pdxml value="PublicationDate_xml"}
      {if $record.$pdxml || $record.PublicationDate}
      <tr valign="top">
        <th>{translate text='Published'}: </th>
        <td>
        {if $record.$pdxml}
    {if $record.$pdxml.0.month}{$record.$pdxml.0.month|escape}/{/if}{if $record.$pdxml.0.day}{$record.$pdxml.0.day|escape}/{/if}{if $record.$pdxml.0.year}{$record.$pdxml.0.year|escape}{/if}
        {else}
    {$record.PublicationDate.0|escape}
        {/if}
        </td>
      </tr>
      {/if}

      {if $record.ISSN}
      <tr valign="top">
        <th>{translate text='ISSN'}: </th>
        <td>
        {foreach from=$record.ISSN item="value"}
    {$value|escape}<br/>
        {/foreach}
        </td>
      </tr>
      {/if}
      
      {if $record.RelatedAuthor}
      <tr valign="top">
        <th>{translate text='Related Author'}: </th>
        <td>
    {foreach from=$record.RelatedAuthor item="author"}
    <a href="{$url}/Summon/Search?type=Author&amp;lookfor={$author|escape:"url"}">{$author|escape}</a>
    {/foreach}
        </td>
      </tr>
      {/if}

      {if $record.Volume}
      <tr valign="top">
        <th>{translate text='Volume'}: </th>
        <td>{$record.Volume.0|escape}</td>
      </tr>
      {/if}

      {if $record.Issue}
      <tr valign="top">
        <th>{translate text='Issue'}: </th>
        <td>{$record.Issue.0|escape}</td>
      </tr>
      {/if}

      {if $record.StartPage}
      <tr valign="top">
        <th>{translate text='Start Page'}: </th>
        <td>{$record.StartPage.0|escape}</td>
      </tr>
      {/if}

      {if $record.EndPage}
      <tr valign="top">
        <th>{translate text='End Page'}: </th>
        <td>{$record.EndPage.0|escape}</td>
      </tr>
      {/if}

      {if $record.Language}
      <tr valign="top">
        <th>{translate text='Language'}: </th>
        <td>{$record.Language.0|escape}</td>
      </tr>
      {/if}

      {if $record.SubjectTerms}
      <tr valign="top">
        <th>{translate text='Subjects'}: </th>
        <td>
    {foreach from=$record.SubjectTerms item=field name=loop}
      <a href="{$path}/Summon/Search?type=SubjectTerms&amp;lookfor=%22{$field|escape:"url"}%22">{$field|escape}</a><br/>
    {/foreach}
        </td>
      </tr>
      {/if}

      {* TODO: Fix Summon tag support:
      <tr valign="top">
        <th>{translate text='Tags'}: </th>
        <td>
    <span style="float:right;">
      <a href="{$url}/Record/{$id}/AddTag" class="tool add"
         onClick="getLightbox('Record', 'AddTag', '{$id}', null, '{translate text="Add Tag"}'); return false;">{translate text="Add"}</a>
    </span>
    <div id="tagList">
      {if $tagList}
        {foreach from=$tagList item=tag name=tagLoop}
      <a href="{$url}/Search/Home?tag={$tag->tag}">{$tag->tag}</a> ({$tag->cnt}){if !$smarty.foreach.tagLoop.last}, {/if}
        {/foreach}
      {else}
        {translate text='No Tags'}, {translate text='Be the first to tag this record'}!
      {/if}
    </div>
        </td>
      </tr>
       *}
    </table>
    {* End Main Details *}
    
  </div>
  {* End Record *} 
  
  {* Add COINS *}  
  <span class="Z3988" title="{$record.openUrl|escape}"></span>
</div>

<div class="span-5 {if $sidebarOnLeft}pull-18 sidebarOnLeft{else}last{/if}">
</div>

<div class="clear"></div>
