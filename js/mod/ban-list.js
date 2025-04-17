/*
 * ban-list.js
 *
 * Released under the MIT license
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 */

document.addEventListener('DOMContentLoaded', function() {
    const banlistToken = document.getElementById('banlist').dataset.token;
    const inMod = document.getElementById('banlist').dataset.ismod === 'true';
    banlist_init(banlistToken, inMod);
});

const banlist_init = (token, inMod) => {

  let selected = {};
  let gridApi;
  let tooltipDiv;
  const time = () => Math.floor(Date.now() / 1000);

  const fetchData = async () => {
    try {
      const response = await fetch(inMod ? `?/bans.json/${token}` : token);
      if (!response.ok) {
        throw new Error(`Network response was not ok: ${response.statusText}`);
      }
      return await response.json();
    } catch (error) {
      console.error('Error fetching data:', error);
      alert('Failed to load ban list data.');
      return [];
    }
  };

  const expiredStyle = (expires) => {
    if (expires && expires !== 0 && expires < time()) return { textDecoration: 'line-through' }; 
  };

  const isMobile = window.innerWidth < 768;

  const createColumnDefs = () => [
    {
      headerName: _('Unban'),
      field: 'unban',
      width: 10,
      checkboxSelection: true,
      hide: !inMod,
    },
    {
      headerName: _('ID'),
      field: 'id',
      width: 10,
      hide: isMobile,
      filter: true,
      filterParams: {
        filterOptions: ["equals"],
        buttons: ["reset", "apply"],
        closeOnApply: true,
        maxNumConditions: 1,
      },
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _('Mask'),
      field: 'mask',
      width: isMobile ? 70 : 50,
      filter: true,
      filterParams: {
        filterOptions: ["equals"],
        buttons: ["reset", "apply"],
        closeOnApply: true,
        maxNumConditions: 1,
      },
      cellRenderer: ({ data, value }) => {
        return inMod && data.single_addr && !data.masked
          ? `<a href="?/user_posts/ip/${value}">${data.mask_human_readable}</a>`
          : data.mask_human_readable;
      },
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Reason"),
      field: 'reason',
      width: isMobile ? 140 : 120,
      wrapText: true,
      autoHeight: true,
      filter: 'agTextColumnFilter',
      cellRenderer: ({ data, value }) => {
        let add = data.message ? `<i class='fa fa-comment' style="margin-right: 5px;" title='${_("Message for which user was banned is included. Click to see.")}'></i>` : '';
            add += data.seen ? `<i class='fa fa-eye' style='font-size: 16px;' title='${_('Seen')}'></i>`
                            : `<i class='fa fa-eye-slash' style='font-size: 16px;' title='${_('Not Seen')}'></i>`;
        return `<div style='float: right;'>${add}</div>${value || '-'}`;
      },
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Board"),
      field: 'board',
      width: 20,
      hide: isMobile,
      filter: true,
      filterParams: {
        filterOptions: ["equals", "notEqual"],
        buttons: ["reset", "apply"],
        closeOnApply: true,
        maxNumConditions: 1
      },
      cellRenderer: ({ value }) => value ? `/${value}/` : `<em>${_("all")}</em>`,
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Set"),
      field: 'created',
      width: isMobile ? 60 : 40,
      sort: 'desc',
      valueFormatter: ({ value }) => `${ago(value)}${_(" ago")}`,
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Expires"),
      field: 'expires',
      width: isMobile ? 60 : 40,
      cellRenderer: ({ value }) => !value || value === 0
        ? `<em>${_("never")}</em>`
        : `${strftime(window.post_date, new Date((value | 0) * 1000), datelocale)} ${(value < time()) ? "" : " <small>" + _("in ") + until(value | 0) + "</small>"}`,
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Staff"),
      field: 'username',
      width: 20,
      hide: isMobile,
      filter: true,
      filterParams: {
        filterOptions: ["equals", "notEqual"],
        buttons: ["reset", "apply"],
        closeOnApply: true,
        maxNumConditions: 1
      },
      cellRenderer: ({ data, value }) => {
        const pre = inMod && value && value !== '?' && !data.vstaff ? `<a href='?/new_PM/${value}'>` : '';
        const suf = pre ? '</a>' : '';
        return `${pre}${value || `<em>${_("system")}</em>`}${suf}`;
      },
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
    {
      headerName: _("Edit"),
      field: 'id',
      width: 20,
      hide: !inMod || isMobile,
      cellRenderer: ({ value }) => `<a href='?/edit_ban/${value}'>Edit</a>`,
      cellStyle: ({ data }) => expiredStyle(data.expires)
    },
  ];

  const setupGrid = (data) => {
    const columnDefs = createColumnDefs();

    const gridOptions = {
      columnDefs,
      rowData: data,
      localeText: AG_GRID_LOCALE_BR,
      rowSelection: 'multiple',
      enableCellTextSelection: true,
      suppressMovableColumns: true,
      pagination: true,
      paginationPageSize: 50,
      paginationPageSizeSelector: [50, 100, 200, 300, 500],
      domLayout: 'autoHeight',
      suppressHorizontalScroll: false,
      onGridReady: ({ api }) => {
        api.sizeColumnsToFit();
        gridApi = api;
      },
      onSelectionChanged: ({ api }) => {
        selected = {};
        api.getSelectedNodes().forEach(node => {
          selected[node.data.id] = true;
        });
      },
      onCellClicked: ({ colDef, data, event }) => {
        if (colDef.field === 'reason' && data.message) toggleTooltip(event, data.message);
      },
    };

    const gridDiv = document.querySelector('#banlist');
    agGrid = new agGrid.createGrid(gridDiv, gridOptions);

    setupEventListeners();
  };

  const setupEventListeners = () => {
    const searchInput = document.querySelector('#search');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        gridApi.setGridOption('quickFilterText', searchInput.value);
      });
    }

    const selectAll = document.querySelector('#select-all');
    if (selectAll) {
      selectAll.addEventListener('click', () => {
        const checked = selectAll.checked;
        gridApi.forEachNode(node => {
          node.setSelected(checked);
          if (node.data.access) selected[node.data.id] = checked;
        });
      });
    }

    document.querySelector(".banform").addEventListener('submit', e => e.preventDefault());

    const unbanBtn = document.querySelector("#unban");
    if (unbanBtn) {
      unbanBtn.addEventListener('click', handleUnban);
    }
  };

  const handleUnban = () => {
    if (confirm(_('Are you sure you want to unban the selected IPs?'))) {
      document.querySelectorAll(".banform .hiddens").forEach(el => el.remove());

      Vichan.createElement('input', {
          attributes: { type: 'hidden', name: 'unban', value: 'unban' },
          className: 'hiddens',
          parent: document.querySelector(".banform")
      });

      Object.keys(selected).forEach(e => {
          Vichan.createElement('input', {
              attributes: { type: 'hidden', name: `ban_${e}`, value: 'unban' },
              className: 'hiddens',
              parent: document.querySelector(".banform")
          });
      });

      document.querySelector(".banform").submit();
    }
  };

  const showTooltip = (event, message) => {
    if (!tooltipDiv) {
      tooltipDiv = Vichan.createElement('div', {
          className: 'post reply',
          attributes: {
              style: 'position: absolute; padding-right: 10px; max-width: 50%; z-index: 1000;',
          },
          parent: document.body
      });

      const introPara = Vichan.createElement('p', {
          className: 'intro',
          parent: tooltipDiv
      });

      Vichan.createElement('span', {
          className: 'name',
          text: 'Anônima',
          parent: introPara
      });

      Vichan.createElement('time', {
          text: message.date,
          attributes: { style: 'margin-left: 10px;' },
          parent: introPara
      });

      Vichan.createElement('a', {
          className: 'post_no',
          text: `No.${message.id}`,
          attributes: { style: 'margin-left: 10px;' },
          parent: introPara
      });

      const bodyDiv = Vichan.createElement('div', {
          className: 'body',
          attributes: { style: 'all: unset;' },
          parent: tooltipDiv
      });

      Vichan.createElement('p', {
          innerHTML: message.post,
          parent: bodyDiv
      });
    }

    updateTooltipPosition(event);
  };

  const hideTooltip = () => {
    if (tooltipDiv) {
      document.body.removeChild(tooltipDiv);
      tooltipDiv = null;
    }
  };

  const toggleTooltip = (event, message) => {
    if (!tooltipDiv || tooltipDiv.style.display === 'none') {
      showTooltip(event, message);
    } else {
      hideTooltip();
    }
  };

  const updateTooltipPosition = (event) => {
    if (tooltipDiv) {
      tooltipDiv.style.left = `${event.clientX + 10}px`;
      tooltipDiv.style.top = `${event.clientY + window.scrollY + 10}px`;
      tooltipDiv.style.display = 'block';
    }
  };

  document.addEventListener('click', event => {
    if (tooltipDiv && !tooltipDiv.contains(event.target)) hideTooltip();
  });

  document.addEventListener('scroll', event => {
    if (tooltipDiv && tooltipDiv.style.display === 'block') {
      updateTooltipPosition(event);
    }
  }, true);

  fetchData().then(setupGrid);
};
