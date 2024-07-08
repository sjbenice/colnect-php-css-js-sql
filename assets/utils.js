function validateURL(url) {
    const pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
        '((([a-zA-Z0-9\\-\\.]+)\\.([a-zA-Z]{2,})|'+ // domain name and extension
        '((\\d{1,3}\\.){3}\\d{1,3}))|'+ // OR ip (v4) address
        'localhost)'+ // OR localhost
        '(\\:\\d+)?'+ // port
        '(\\/[-a-zA-Z0-9@:%_\\+.~#?&//=]*)?'+ // path
        '(\\?[;&a-zA-Z0-9%_\\.~+=-]*)?'+ // query string
        '(\\#[-a-zA-Z0-9_]*)?$','i'); // fragment locator
    return pattern.test(url);
}

function validateTag(tag) {
    const validTagPattern = /^[a-zA-Z][a-zA-Z0-9]*$/;
    return validTagPattern.test(tag);
}

function showHideItem(item, isShow, showCmd="block") {
    if (item) {
        item.style.display = isShow ? showCmd : "none";
    }
}

function enableDisable(item, isEnable) {
    if (item) {
        item.disabled = !isEnable;
    }
}
