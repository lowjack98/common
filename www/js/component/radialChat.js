// vim600: fdm=marker
///////////////////////////////////////////
// author     : Ben Kietzman
// begin      : 2024-01-26
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
    let s = c.store('radialChat',
    {
      // {{{ u()
      u: () =>
      {
        c.render(id, this);
      },
      // }}}
      c: c,
      notify: false,
      history: [],
      menu: false,
      message: null,
      user: new Observable,
      users: {}
    });
    // }}}
    // {{{ chat()
    s.chat = () =>
    {
      if (c.isValid() && s.user.v)
      {
        let message = {Action: 'chat', Message: s.message.v, User: c.getUserID(), FirstName: c.getUserFirstName(), LastName: c.getUserLastName()};
        s.history.push(message);
        while (s.history.length > 50)
        {
          s.history.shift();
        }
        let request = {Interface: 'live', 'Function': 'message', Request: {User: s.user.v, Message: message}};
        s.message.v = null;
        c.wsRequest(c.m_strAuthProtocol, request).then((response) => {});
        s.u();
        document.getElementById('history').scrollTop = document.getElementById('history').scrollHeight;
        document.getElementById('message').focus();
      }
    };
    // }}}
    // {{{ enter()
    s.enter = () =>
    {
      if (window.event.keyCode == 13)
      {
        s.chat();
      }
    };
    // }}}
    // {{{ slide()
    s.slide = () =>
    {
      s.menu = !s.menu;
      if (s.menu)
      {
        s.notify = false;
      }
      s.u();
      if (s.menu)
      {
        document.getElementById('message').focus();
        document.getElementById('history').scrollTop = document.getElementById('history').scrollHeight;
      }
    };
    // }}}
    // {{{ init()
    s.init = () =>
    {
      c.attachEvent('commonWsMessage_'+c.application, (data) =>
      {
        if (c.isDefined(data.detail) && c.isDefined(data.detail.Action) && c.isDefined(data.detail.User))
        {
          // {{{ chat
          if (data.detail.Action == 'chat' && c.isDefined(data.detail.Message))
          {
            if (!s.menu)
            {
              s.notify = true;
            }
            s.history.push(data.detail);
            while (s.history.length > 50)
            {
              s.history.shift();
            }
            if (c.getUserID() != data.detail.User && s.user.v != data.detail.User)
            {
              s.user.v = data.detail.User;
            }
            s.u();
            if (s.menu)
            {
              document.getElementById('message').focus();
              document.getElementById('history').scrollTop = document.getElementById('history').scrollHeight;
            }
          }
          // }}}
          // {{{ connect
          else if (data.detail.Action == 'connect')
          {
            if (c.getUserID() != data.detail.User)
            {
              if (!s.users[data.detail.User])
              {
                s.users[data.detail.User] = {FirstName: data.detail.FirstName, LastName: data.detail.LastName, sessions: []};
              }
              s.users[data.detail.User].sessions.push(data.detail.wsRequestID);
              s.u();
            }
          }
          // }}}
          // {{{ disconnect
          else if (data.detail.Action == 'disconnect')
          {
            if (c.getUserID() != data.detail.User && c.isObject(s.users) && s.users[data.detail.User])
            {
              let sessions = [];
              for (let i = 0; i < s.users[data.detail.User].length; i++)
              {
                if (s.users[data.detail.User].sessions[i] != data.detail.wsRequestID)
                {
                  sessions.push(s.users[data.detail.User].sessions[i]);
                }
              }
              if (sessions.length > 0)
              {
                s.users[data.detail.User].sessions = null;
                s.users[data.detail.User].sessions = sessions;
              }
              else
              {
                delete s.users[data.detail.User];
              }
              s.u();
            }
          }
          // }}}
        }
      });
      s.users = null;
      s.users = {};
      let request = {Interface: 'live', 'Function': 'list', Request: {Scope: 'all'}};
      c.wsRequest(c.m_strAuthProtocol, request).then((response) =>
      {
        let error = {};
        if (c.wsResponse(response, error) && c.isDefined(response.Response))
        {
          for (let [strSession, data] of Object.entries(response.Response))
          {
            if (c.isDefined(data.User) && c.getUserID() != data.User)
            {
              if (!s.users[data.User])
              {
                s.users[data.User] = {FirstName: data.FirstName, LastName: data.LastName, sessions: []};
              }
              s.users[data.User].sessions.push(data.wsRequestID);
            }
          }
          s.u();
        }
      });
    };
    // }}}
    // {{{ main
    c.attachEvent('commonWsConnected', (data) =>
    {
      s.init();
    });
    // }}}
  },
  // }}}
  // {{{ template
  template: `
    {{#isValid}}
    <div style="position: relative; z-index: 999;">
      <div id="radial-slide-panel" class="bg-{{#if @root.notify}}warning{{else}}info{{/if}}" style="position: fixed; top: 180px; right: 0px;">
        <button id="radial-slide-opener" class="btn btn-sm btn-{{#if @root.notify}}warning{{else}}info{{/if}} float-start" c-click="slide()" style="width: 33px; height: 33px; font-size: 18px; font-weight: bold; margin: 0px 0px 0px -33px; border-radius: 10px 0px 0px 10px; vertical-align: top;" title="chat"><i class="bi bi-chat"></i></button>
        {{#if @root.menu}}
        <div id="radial-slide-content" style="padding: 10px;">
          <select class="form-control form-control-sm" c-model="user" style="margin-bottom: 10px;">
            {{#each @root.users}}
            <option value="{{@key}}">{{LastName}}, {{FirstName}} ({{@key}})</option>
            {{/each}}
          </select>
          <div class="card card-body card-inverse table-responsive" id="history" style="max-height: 200px; max-width: 400px;">
            <table class="table table-condensed table-striped">
              {{#each @root.history}}
              <tr>
                <td>{{LastName}}, {{FirstName}} ({{User}})</td>
                <td>{{Message}}</td>
              </tr>
              {{/each}}
            </table>
          </div>
          <input type="text" class="form-control form-control-sm" id="message" c-model="message" c-keyup="enter()" placeholder="Type message and hit enter..." style="margin-top: 10px;">
        </div>
        {{/if}}
      </div>
    </div>
    {{/isValid}}
  `
  // }}}
}
