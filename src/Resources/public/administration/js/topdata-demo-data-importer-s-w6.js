(function(){"use strict";var t={};t.p="bundles/topdatademodataimportersw6/",window?.__sw__?.assetPath&&(t.p=window.__sw__.assetPath+"/bundles/topdatademodataimportersw6/"),function(){Shopware.Component.register("topdata-demo-data-index",{template:'{# Template for the demo data import page #}\n{# Uses Shopware\'s UI components for consistent styling #}\n<sw-page class="topdata-demo-data-index">\n    <template #content>\n        <sw-card-view>\n            {# Main card containing the import button #}\n            <sw-card\n                :isLoading="isLoading"\n                :large="true">\n                {# Primary action button that triggers the import #}\n                <sw-button\n                    variant="primary"\n                    size="large"\n                    @click="importDemoData">\n                    {{ $tc(\'topdata-demo-data.general.importButton\') }}\n                </sw-button>\n            </sw-card>\n        </sw-card-view>\n    </template>\n</sw-page>\n',inject:["TopdataDemoDataApiService"],data(){return{isLoading:!1}},methods:{importDemoData(){this.isLoading=!0,this.TopdataDemoDataApiService.installDemoData().finally(()=>{this.isLoading=!1})}}});class t{client;constructor(){this.client=Shopware.Service().get("TopdataAdminApiClient")}installDemoData(){return this.client.get("/topdata-demo-data/install-demodata")}}Shopware.Module.register("topdata-demo-data",{type:"plugin",name:"Topdata Demo Data",title:"topdata-demo-data.general.mainMenuTitle",description:"topdata-demo-data.general.descriptionTextModule",color:"#ff3d58",routes:{index:{component:"topdata-demo-data-index",path:"index"}},navigation:[{label:"topdata-demo-data.general.mainMenuTitle",color:"#ff3d58",path:"topdata.demo.data.index",icon:"regular-database",position:100,parent:"sw-content"}]}),Shopware.Service().register("TopdataDemoDataApiService",()=>new t)}()})();