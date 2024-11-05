(()=>{"use strict";var e,t={49:()=>{const e=window.wp.blocks,t=window.wp.i18n,r=window.wp.blockEditor,i=window.wp.components,o=window.ReactJSXRuntime,n=JSON.parse('{"UU":"wp-remote-jobs/registration"}');(0,e.registerBlockType)(n.UU,{icon:{src:(0,o.jsx)("svg",{id:"Layer_1_1_",style:{enableBackground:"new 0 0 64 64"},version:"1.1",viewBox:"0 0 64 64",xmlns:"http://www.w3.org/2000/svg",children:(0,o.jsxs)("g",{children:[(0,o.jsx)("path",{d:"M60,3h-9.184C50.402,1.839,49.302,1,48,1H34c-1.302,0-2.402,0.839-2.816,2H22c-1.654,0-3,1.346-3,3v13h-3.406 c-1.217,0-2.418,0.319-3.474,0.923L6.734,23H1v18h6.697l4.236,2.824C13.087,44.594,14.43,45,15.816,45H19v15c0,1.654,1.346,3,3,3 h38c1.654,0,3-1.346,3-3V6C63,4.346,61.654,3,60,3z M25,27h2c2.206,0,4-1.794,4-4s-1.794-4-4-4h-2V9h6.184 c0.414,1.161,1.514,2,2.816,2h14c1.302,0,2.402-0.839,2.816-2H57v48H25V27z M33,4c0-0.552,0.448-1,1-1h14c0.552,0,1,0.448,1,1v4 c0,0.552-0.448,1-1,1H34c-0.552,0-1-0.448-1-1V4z M21,6c0-0.552,0.448-1,1-1h9v2h-8v12h-2V6z M15.816,43 c-0.99,0-1.949-0.29-2.773-0.84L8.303,39H3V25h4.266l5.847-3.341C13.867,21.228,14.725,21,15.594,21H27c1.103,0,2,0.897,2,2 s-0.897,2-2,2H15v1c0,2.757-2.243,5-5,5v2c3.521,0,6.442-2.612,6.929-6H19v16H15.816z M61,60c0,0.552-0.448,1-1,1H22 c-0.552,0-1-0.448-1-1V27h2v32h36V7h-8V5h9c0.552,0,1,0.448,1,1V60z"}),(0,o.jsx)("rect",{height:"2",width:"2",x:"35",y:"5"}),(0,o.jsx)("rect",{height:"2",width:"2",x:"45",y:"5"}),(0,o.jsx)("path",{d:"M48.373,47.209l-3.375-0.964l-0.001-0.507C46.81,44.472,48,42.374,48,40v-2c0-3.859-3.141-7-7-7s-7,3.141-7,7v2 c0,2.372,1.189,4.469,3,5.736v0.51l-3.374,0.963C31.491,47.82,30,49.797,30,52.018V55h22v-2.982 C52,49.797,50.509,47.82,48.373,47.209z M36,40v-2c0-2.757,2.243-5,5-5s5,2.243,5,5v2c0,2.757-2.243,5-5,5S36,42.757,36,40z M42.965,46.714L41,49.333l-1.965-2.619C39.659,46.897,40.318,47,41,47S42.341,46.897,42.965,46.714z M50,53H32v-0.982 c0-1.332,0.895-2.519,2.176-2.885l3.437-0.982L41,52.667l3.387-4.516l3.437,0.982C49.105,49.499,50,50.686,50,52.018V53z"}),(0,o.jsx)("rect",{height:"2",width:"2",x:"27",y:"13"}),(0,o.jsx)("rect",{height:"2",width:"24",x:"31",y:"13"}),(0,o.jsx)("rect",{height:"2",width:"22",x:"33",y:"17"}),(0,o.jsx)("rect",{height:"2",width:"22",x:"33",y:"21"}),(0,o.jsx)("rect",{height:"2",width:"2",x:"53",y:"25"}),(0,o.jsx)("rect",{height:"2",width:"18",x:"33",y:"25"})]})})},edit:function({attributes:e,setAttributes:n}){const{companyName:s,companyHQ:a,logo:l,websiteURL:c,email:h,description:v}=e;return(0,o.jsx)("div",{...(0,r.useBlockProps)(),children:(0,o.jsxs)("div",{className:"registration-form",children:[(0,o.jsx)("h2",{children:(0,t.__)("Registration Form","registration")}),(0,o.jsx)("p",{children:(0,t.__)("Please fill out the form below:","registration")}),(0,o.jsx)(i.TextControl,{label:(0,t.__)("Company Name","registration"),value:s,onChange:e=>n({companyName:e})}),(0,o.jsx)(i.TextControl,{label:(0,t.__)("Company HQ","registration"),value:a,onChange:e=>n({companyHQ:e})}),(0,o.jsx)(r.MediaUploadCheck,{children:(0,o.jsx)(r.MediaUpload,{onSelect:e=>n({logo:e.url}),allowedTypes:["image"],value:l,render:({open:e})=>(0,o.jsx)(i.Button,{onClick:e,children:l?(0,t.__)("Change Logo","registration"):(0,t.__)("Upload Logo","registration")})})}),(0,o.jsx)(i.TextControl,{label:(0,t.__)("Company Website URL","registration"),value:c,onChange:e=>n({websiteURL:e})}),(0,o.jsx)(i.TextControl,{label:(0,t.__)("Email","registration"),type:"email",value:h,onChange:e=>n({email:e})}),(0,o.jsx)(i.TextareaControl,{label:(0,t.__)("Company Description","registration"),value:v,onChange:e=>n({description:e})})]})})},save:function(){return null}})}},r={};function i(e){var o=r[e];if(void 0!==o)return o.exports;var n=r[e]={exports:{}};return t[e](n,n.exports,i),n.exports}i.m=t,e=[],i.O=(t,r,o,n)=>{if(!r){var s=1/0;for(h=0;h<e.length;h++){r=e[h][0],o=e[h][1],n=e[h][2];for(var a=!0,l=0;l<r.length;l++)(!1&n||s>=n)&&Object.keys(i.O).every((e=>i.O[e](r[l])))?r.splice(l--,1):(a=!1,n<s&&(s=n));if(a){e.splice(h--,1);var c=o();void 0!==c&&(t=c)}}return t}n=n||0;for(var h=e.length;h>0&&e[h-1][2]>n;h--)e[h]=e[h-1];e[h]=[r,o,n]},i.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={57:0,350:0};i.O.j=t=>0===e[t];var t=(t,r)=>{var o,n,s=r[0],a=r[1],l=r[2],c=0;if(s.some((t=>0!==e[t]))){for(o in a)i.o(a,o)&&(i.m[o]=a[o]);if(l)var h=l(i)}for(t&&t(r);c<s.length;c++)n=s[c],i.o(e,n)&&e[n]&&e[n][0](),e[n]=0;return i.O(h)},r=self.webpackChunkregistration=self.webpackChunkregistration||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var o=i.O(void 0,[350],(()=>i(49)));o=i.O(o)})();