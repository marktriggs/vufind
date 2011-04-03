<div data-role="page" id="MyResearch-checkedout">
  {include file="header.tpl"}
  <div data-role="content">
    {if $user->cat_username}
      <h3>{translate text='Your Checked Out Items'}</h3>
      {if $transList}
        <ul class="results checkedout-list" data-role="listview">
        {foreach from=$transList item=resource name="recordLoop"}
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
            <p><strong>{translate text='Due'}</strong>: {$resource.duedate|escape}</p>
            </div>
            </a>
          </li>
        {/foreach}
        </ul>
      {else}
        <p>{translate text='You do not have any items checked out'}.</p>
      {/if}
    {else}
      {include file="MyResearch/catalog-login.tpl"}
    {/if}
  </div>
  {include file="footer.tpl"}
</div>
