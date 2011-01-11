function SendEmail(id, to, from, message, strings)
{
    var url = path + "/WorldCat/AJAX";
    var params = "id=" + encodeURIComponent(id) + "&" +
                 "from=" + encodeURIComponent(from) + "&" +
                 "to=" + encodeURIComponent(to) + "&" +
                 "message=" + encodeURIComponent(message);
    sendAJAXEmail(url, params, strings);
}
