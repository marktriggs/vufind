  {if $errorMsg}<div class="error">{$errorMsg|translate}</div>{/if}
  {if $infoMsg}<div class="info">{$infoMsg|translate}</div>{/if}

  <form action="{$url}/WorldCat/Email?id={$id|escape:"url"}" method="post" id="popupForm" name="popupForm">
    <label class="displayBlock" for="email_to">{translate text='To'}:</label>
    <input id="email_to" type="text" name="to" size="40"/>
    <label class="displayBlock" for="email_from">{translate text='From'}:</label>
    <input id="email_from" type="text" name="from" size="40"/>
    <label class="displayBlock" for="email_message">{translate text='Message'}:</label>
    <textarea id="email_message" name="message" rows="3" cols="40"></textarea>
    <br/>
    <input class="button" type="submit" name="submit" value="{translate text='Send'}"/>
  </form>
