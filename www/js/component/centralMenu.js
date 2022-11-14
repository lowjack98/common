// vim600: fdm=marker
///////////////////////////////////////////
// author     : Ben Kietzman
// begin      : 2022-11-09
// copyright  : kietzman.org
// email      : ben@kietzman.org
///////////////////////////////////////////
export default
{
  // {{{ controller()
  controller(id)
  {
    // {{{ prep work
    let c = common;
    let s = c.store('centralMenu',
    {
      b:
      {
        application: new Observable
      },
      c: c,
      go: () =>
      {
        document.location.href = c.centralMenu.applications[s.b.application.value].website;
      },
      slideMenu: () =>
      {
        c.centralMenu.show = !c.centralMenu.show;
        c.render(id, this);
      }
    });
    // }}}
    // {{{ main
    if (!c.isDefined(c.centralMenu.applications))
    {
      c.request('applications', {_script: c.centralScript}, (data) =>
      {
        let error = {};
        if (c.response(data, error))
        {
          let unIndex = 0;
          c.centralMenu.applications = [];
          for (let i = 0; i < data.Response.out.length; i++)
          {
            if (((data.Response.out[i].menu_id == 1 && c.isValid()) || data.Response.out[i].menu_id == 2) && (data.Response.out[i].retirement_date == null || data.Response.out[i].retirement_date == '0000-00-00 00:00:00'))
            {
              data.Response.out[i].i = unIndex;
              c.centralMenu.applications.push(data.Response.out[i]);
              if (data.Response.out[i].name == c.application)
              {
                s.b.application.value = unIndex;
              }
              unIndex++;
            }
          }
        }
        c.render(id, this);
      });
    }
    // }}}
  },
  // }}}
  // {{{ template
  template: `
    <div style="position: relative; z-index: 1000;">
      <div id="central-slide-panel" class="bg-success" style="position: fixed; top: 120px; right: 0px;">
        <button id="central-slide-opener" class="btn btn-sm btn-success float-start" c-click="slideMenu()" style="font-size: 18px; font-weight: bold; margin: 0px 0px 0px -33px; border-radius: 10px 0px 00px 10px;">&#8803;</button>
        {{#if c.centralMenu.show}}
        <div id="central-slide-content" style="padding: 10px;">
          <select class="form-control form-control-sm" c-change="go()" c-model="application">
            {{#each c.centralMenu.applications}}
            <option value="{{i}}">{{name}}</option>
            {{/each}}
          </select>
        </div>
        {{/if}}
      </div>
    </div>
  `
  // }}}
}
