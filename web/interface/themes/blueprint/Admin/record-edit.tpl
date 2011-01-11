<div class="span-5">
  {include file="Admin/menu.tpl"}
</div>

<div class="span-18 last">
  <h1>{if $allowChanges}{translate text="Edit Record"}{else}{translate text="View Record"}{/if}</h1>

  {if $record}
    <form method="post" action="{$url}/Admin/Records">
    <table class="citation">
    {foreach from=$record item=value key=field}
      {if is_array($value)}
        {foreach from=$value item=current}
        <tr>
          <th>{$field}: </th>
          <td>
            {if $allowChanges}
              <input type="text" name="solr_{$field}[]" value="{$current|escape}" size="50"/>
            {else}
              <div class="fieldValue">{$current|regex_replace:"/[\x1D\x1E\x1F]/":""|escape}</div>
            {/if}
          </td>
        </tr>
        {/foreach}
      {else}
        <tr>
          <th>{$field}: </th>
          <td>
            {if $allowChanges}
              <input type="text" name="solr_{$field}[]" value="{$value|escape}" size="50"/>
            {else}
              <div class="fieldValue">{$value|regex_replace:"/[\x1D\x1E\x1F]/":""|escape}</div>
            {/if}
          </td>
        </tr>
      {/if}
    {/foreach}
    </table>
    {if $allowChanges}
      <input type="submit" name="submit" value="{translate text="Save"}"/>
    {/if}
    </form>
  {else}
    <p>Could not load record {$recordId|escape}.</p>
  {/if}
</div>

<div class="clear"></div>
