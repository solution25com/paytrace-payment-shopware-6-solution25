(()=>{"use strict";var e={857:e=>{var t=function(e){var t;return!!e&&"object"==typeof e&&"[object RegExp]"!==(t=Object.prototype.toString.call(e))&&"[object Date]"!==t&&e.$$typeof!==r},r="function"==typeof Symbol&&Symbol.for?Symbol.for("react.element"):60103;function n(e,t){return!1!==t.clone&&t.isMergeableObject(e)?a(Array.isArray(e)?[]:{},e,t):e}function i(e,t,r){return e.concat(t).map(function(e){return n(e,r)})}function o(e){return Object.keys(e).concat(Object.getOwnPropertySymbols?Object.getOwnPropertySymbols(e).filter(function(t){return Object.propertyIsEnumerable.call(e,t)}):[])}function s(e,t){try{return t in e}catch(e){return!1}}function a(e,r,c){(c=c||{}).arrayMerge=c.arrayMerge||i,c.isMergeableObject=c.isMergeableObject||t,c.cloneUnlessOtherwiseSpecified=n;var l,d,u=Array.isArray(r);return u!==Array.isArray(e)?n(r,c):u?c.arrayMerge(e,r,c):(d={},(l=c).isMergeableObject(e)&&o(e).forEach(function(t){d[t]=n(e[t],l)}),o(r).forEach(function(t){(!s(e,t)||Object.hasOwnProperty.call(e,t)&&Object.propertyIsEnumerable.call(e,t))&&(s(e,t)&&l.isMergeableObject(r[t])?d[t]=(function(e,t){if(!t.customMerge)return a;var r=t.customMerge(e);return"function"==typeof r?r:a})(t,l)(e[t],r[t],l):d[t]=n(r[t],l))}),d)}a.all=function(e,t){if(!Array.isArray(e))throw Error("first argument should be an array");return e.reduce(function(e,r){return a(e,r,t)},{})},e.exports=a}},t={};function r(n){var i=t[n];if(void 0!==i)return i.exports;var o=t[n]={exports:{}};return e[n](o,o.exports,r),o.exports}(()=>{r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t}})(),(()=>{r.d=(e,t)=>{for(var n in t)r.o(t,n)&&!r.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})}})(),(()=>{r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t)})(),(()=>{var e=r(857),t=r.n(e);class n{static ucFirst(e){return e.charAt(0).toUpperCase()+e.slice(1)}static lcFirst(e){return e.charAt(0).toLowerCase()+e.slice(1)}static toDashCase(e){return e.replace(/([A-Z])/g,"-$1").replace(/^-/,"").toLowerCase()}static toLowerCamelCase(e,t){let r=n.toUpperCamelCase(e,t);return n.lcFirst(r)}static toUpperCamelCase(e,t){return t?e.split(t).map(e=>n.ucFirst(e.toLowerCase())).join(""):n.ucFirst(e.toLowerCase())}static parsePrimitive(e){try{return/^\d+(.|,)\d+$/.test(e)&&(e=e.replace(",",".")),JSON.parse(e)}catch(t){return e.toString()}}}class i{static isNode(e){return"object"==typeof e&&null!==e&&(e===document||e===window||e instanceof Node)}static hasAttribute(e,t){if(!i.isNode(e))throw Error("The element must be a valid HTML Node!");return"function"==typeof e.hasAttribute&&e.hasAttribute(t)}static getAttribute(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!1===i.hasAttribute(e,t))throw Error('The required property "'.concat(t,'" does not exist!'));if("function"!=typeof e.getAttribute){if(r)throw Error("This node doesn't support the getAttribute function!");return}return e.getAttribute(t)}static getDataAttribute(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2],o=t.replace(/^data(|-)/,""),s=n.toLowerCamelCase(o,"-");if(!i.isNode(e)){if(r)throw Error("The passed node is not a valid HTML Node!");return}if(void 0===e.dataset){if(r)throw Error("This node doesn't support the dataset attribute!");return}let a=e.dataset[s];if(void 0===a){if(r)throw Error('The required data attribute "'.concat(t,'" does not exist on ').concat(e,"!"));return a}return n.parsePrimitive(a)}static querySelector(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!i.isNode(e))throw Error("The parent node is not a valid HTML Node!");let n=e.querySelector(t)||!1;if(r&&!1===n)throw Error('The required element "'.concat(t,'" does not exist in parent node!'));return n}static querySelectorAll(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!i.isNode(e))throw Error("The parent node is not a valid HTML Node!");let n=e.querySelectorAll(t);if(0===n.length&&(n=!1),r&&!1===n)throw Error('At least one item of "'.concat(t,'" must exist in parent node!'));return n}static getFocusableElements(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document.body;return e.querySelectorAll('\n            input:not([tabindex^="-"]):not([disabled]):not([type="hidden"]),\n            select:not([tabindex^="-"]):not([disabled]),\n            textarea:not([tabindex^="-"]):not([disabled]),\n            button:not([tabindex^="-"]):not([disabled]),\n            a[href]:not([tabindex^="-"]):not([disabled]),\n            [tabindex]:not([tabindex^="-"]):not([disabled])\n        ')}static getFirstFocusableElement(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document.body;return this.getFocusableElements(e)[0]}static getLastFocusableElement(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document,t=this.getFocusableElements(e);return t[t.length-1]}}class o{publish(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},r=arguments.length>2&&void 0!==arguments[2]&&arguments[2],n=new CustomEvent(e,{detail:t,cancelable:r});return this.el.dispatchEvent(n),n}subscribe(e,t){let r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{},n=this,i=e.split("."),o=r.scope?t.bind(r.scope):t;if(r.once&&!0===r.once){let t=o;o=function(r){n.unsubscribe(e),t(r)}}return this.el.addEventListener(i[0],o),this.listeners.push({splitEventName:i,opts:r,cb:o}),!0}unsubscribe(e){let t=e.split(".");return this.listeners=this.listeners.reduce((e,r)=>([...r.splitEventName].sort().toString()===t.sort().toString()?this.el.removeEventListener(r.splitEventName[0],r.cb):e.push(r),e),[]),!0}reset(){return this.listeners.forEach(e=>{this.el.removeEventListener(e.splitEventName[0],e.cb)}),this.listeners=[],!0}get el(){return this._el}set el(e){this._el=e}get listeners(){return this._listeners}set listeners(e){this._listeners=e}constructor(e=document){this._el=e,e.$emitter=this,this._listeners=[]}}class s{init(){throw Error('The "init" method for the plugin "'.concat(this._pluginName,'" is not defined.'))}update(){}_init(){this._initialized||(this.init(),this._initialized=!0)}_update(){this._initialized&&this.update()}_mergeOptions(e){let r=n.toDashCase(this._pluginName),o=i.getDataAttribute(this.el,"data-".concat(r,"-config"),!1),s=i.getAttribute(this.el,"data-".concat(r,"-options"),!1),a=[this.constructor.options,this.options,e];o&&a.push(window.PluginConfigManager.get(this._pluginName,o));try{s&&a.push(JSON.parse(s))}catch(e){throw console.error(this.el),Error('The data attribute "data-'.concat(r,'-options" could not be parsed to json: ').concat(e.message))}return t().all(a.filter(e=>e instanceof Object&&!(e instanceof Array)).map(e=>e||{}))}_registerInstance(){window.PluginManager.getPluginInstancesFromElement(this.el).set(this._pluginName,this),window.PluginManager.getPlugin(this._pluginName,!1).get("instances").push(this)}_getPluginName(e){return e||(e=this.constructor.name),e}constructor(e,t={},r=!1){if(!i.isNode(e))throw Error("There is no valid element given.");this.el=e,this.$emitter=new o(this.el),this._pluginName=this._getPluginName(r),this.options=this._mergeOptions(t),this._initialized=!1,this._registerInstance(),this._init()}}class a extends s{_registerElements(){this.confirmOrderForm=document.forms[this.options.confirmFormId],this.parentCreditCardWrapper=document.getElementById(this.options.parentCreditCardWrapperId),this.clientKey=this.parentCreditCardWrapper.getAttribute("data-client-key"),this.amount=this.parentCreditCardWrapper.getAttribute("data-amount"),this.cardsDropdown=this.parentCreditCardWrapper.getAttribute("data-cardsDropdown")}init(){this._registerElements(),this._populateDropdown(),this._setupPayTrace(),this._bindEvents()}_populateDropdown(){let e=JSON.parse(this.cardsDropdown),t=document.getElementById("saved-cards");t.innerHTML="";let r=document.createElement("option");r.value="",r.textContent="-- Select a saved card --",t.appendChild(r),e.forEach(e=>{let r=document.createElement("option");r.value=e.vaultedCustomerId,r.textContent=e.customerLabel,t.appendChild(r)})}_setupPayTrace(){PTPayment.setup({styles:{},authorization:{clientKey:this.clientKey}}).then(()=>{console.log("PayTrace setup complete")}).catch(e=>{console.error("Error during PayTrace setup:",e)})}_bindEvents(){document.getElementById("ProtectForm").addEventListener("click",e=>{e.preventDefault(),e.stopPropagation(),this._getCardToken()}),document.getElementById("SelectCardButton").addEventListener("click",e=>{e.preventDefault(),this._vaultedPayment()},{once:!0}),document.getElementById("saved-cards").addEventListener("change",e=>{let t=e.target.value,r=document.getElementById("SelectCardButton");t?r.style.display="block":r.style.display="none"})}_getCardToken(){PTPayment.process().then(e=>{console.log("Token received:",e),e.message?this._submitPayment(e.message):console.error("Failed to receive a token:",e)}).catch(e=>{console.error("Error during payment processing:",e)})}_vaultedPayment(){let e=document.getElementById("saved-cards").value,t=this.amount;if(!e){alert("No card");return}this._submitVaultedPayment(e,t)}_submitPayment(e){fetch("/capture-paytrace",{method:"POST",body:JSON.stringify({token:e,amount:this.amount}),headers:{"Content-Type":"application/json"}}).then(e=>e.json()).then(e=>{e.success?(console.log("Transaction Successful:",e),document.getElementById("payTrace-transaction-id").value=e.transactionId,document.getElementById("confirmOrderForm").submit()):console.error("Payment failed:",e.message||"Unknown error")}).catch(e=>{console.error("Payment submission failed:",e)})}_submitVaultedPayment(e,t){console.log("_submitVaultedPayment"),fetch("/vaulted-capture-paytrace",{method:"POST",body:JSON.stringify({selectedCardVaultedId:e,amount:t}),headers:{"Content-Type":"application/json"}}).then(e=>e.json()).then(e=>{e.success?(console.log("Transaction Successful:",e),document.getElementById("payTrace-transaction-id").value=e.transactionId,document.getElementById("confirmOrderForm").submit()):console.error("Payment failed:",e.message||"Unknown error")}).catch(e=>{console.error("Payment submission failed:",e)})}}a.options={confirmFormId:"confirmOrderForm",parentCreditCardWrapperId:"payTrace_payment"},window.PluginManager.register("PayTraceCreditCardPlugin",a,"[payTrace-payment-credit-card-plugin]")})()})();