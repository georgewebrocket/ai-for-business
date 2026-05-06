/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


function dateToStr14(strDate, locale) {
    var ar = strDate.split("/");
    switch(locale) {
        case "gr":
            return pad(ar[2],4) + pad(ar[1],2) + pad(ar[0],2) + '000000';
            break;
        case "en":
            return pad(ar[2],4) + pad(ar[0],2) + pad(ar[1],2) + '000000';
            break;
        default:
            return pad(ar[2],4) + pad(ar[1],2) + pad(ar[0],2) + '000000';
            break;
    }
    
}

function pad (str, max) {
    str = str.toString();
    return str.length < max ? pad("0" + str, max) : str;
}