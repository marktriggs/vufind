<div data-role="page" id="MyResearch-holds">
  {include file="header.tpl"}
  <div data-role="content">
    {if $user->cat_username}
      <h3>{translate text='Your Holds and Recalls'}</h3>
      {if $recordList}
        <ul class="results holds" data-role="listview">
        {foreach from=$recordList item=resource name="recordLoop"}
          <li>
            <a rel="external" href="{$path}/Record/{$resource.id|escape}">
            <div class="result">
              <h3>{$resource.title|trim:'/:'|escape}</h3>
              {if !empty($resource.author)}
                <p>{translate text='by'} {$resource.author}</p>
              {/if}
              {if !empty($resource.format)}
              <p>
              {foreach from=$resource.format item=format}
                <span class="iconlabel {$format|lower|regex_replace:"/[^a-z0-9]/":""}">{translate text=$format}</span>
              {/foreach}
              </p>
              {/if} 
              <p><strong>{translate text='Created'}:</strong> {$resource.createdate|escape} |
              <strong>{translate text='Expires'}:</strong> {$resource.expiredate|escape}</p>
            </div>
            </a>
          </li>
        {/foreach}
        </ul>
      {else}
        <p>{translate text='You do not have any holds or recalls placed'}.</p>
      {/if}
    {else}
      {include file="MyResearch/catalog-login.tpl"}
    {/if}
  </div>
  {include file="footer.tpl"}
</div>

