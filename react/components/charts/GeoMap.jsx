import React, { useMemo, useState } from 'react';
import { ComposableMap, Geographies, Geography } from 'react-simple-maps';
import './GeoMap.css';

const geoUrl = 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json';

export default function GeoMap({ data }) {
  const [hoveredCountry, setHoveredCountry] = useState(null);

  // Organize data by country code for quick lookup
  const eventsByCode = useMemo(() => {
    const map = {};
    if (data && Array.isArray(data)) {
      data.forEach(item => {
        const code = item.code || 'UNKNOWN';
        map[code] = item.count || 0;
      });
    }
    return map;
  }, [data]);

  // Calculate stats
  const stats = useMemo(() => {
    const events = Object.values(eventsByCode).reduce((sum, count) => sum + count, 0);
    const countries = Object.keys(eventsByCode).length;
    const maxCount = Math.max(...Object.values(eventsByCode), 0);
    return { events, countries, maxCount };
  }, [eventsByCode]);

  // Color function based on event count
  const getColor = (count) => {
    if (!count || count === 0) return '#f0f0f0';
    const intensity = Math.log(count + 1) / Math.log(stats.maxCount + 1);
    return intensity > 0.8 ? '#800026' :
           intensity > 0.6 ? '#BD0026' :
           intensity > 0.4 ? '#E31A1C' :
           intensity > 0.2 ? '#FC4E2A' :
           intensity > 0.1 ? '#FD8D3C' :
           '#FEB24C';
  };

  // Country code mapping from geography properties
  const countryCodeMap = {
    'United States of America': 'US',
    'United Kingdom': 'GB',
    'China': 'CN',
    'India': 'IN',
    'Japan': 'JP',
    'Germany': 'DE',
    'France': 'FR',
    'Brazil': 'BR',
    'Canada': 'CA',
    'Russia': 'RU',
    'Mexico': 'MX',
    'South Korea': 'KR',
    'Spain': 'ES',
    'Italy': 'IT',
    'Netherlands': 'NL',
    'Sweden': 'SE',
    'Australia': 'AU',
    'South Africa': 'ZA',
    'Egypt': 'EG',
    'Nigeria': 'NG',
    'Singapore': 'SG',
    'Hong Kong': 'HK',
    'Taiwan': 'TW',
    'Thailand': 'TH',
    'Malaysia': 'MY',
    'Indonesia': 'ID',
    'Philippines': 'PH',
    'Vietnam': 'VN',
    'Pakistan': 'PK',
    'Bangladesh': 'BD',
    'Argentina': 'AR',
    'Chile': 'CL',
    'Colombia': 'CO',
    'Poland': 'PL',
    'Austria': 'AT',
    'Belgium': 'BE',
    'Switzerland': 'CH',
    'Norway': 'NO',
    'Denmark': 'DK',
  };

  const getCountryCode = (name) => {
    return countryCodeMap[name] || null;
  };

  // Top countries
  const topCountries = useMemo(() => {
    return Object.entries(eventsByCode)
      .map(([code, count]) => ({ code, count }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 10);
  }, [eventsByCode]);

  return (
    <div className="geomap-container">
      <div className="geomap-map">
        <ComposableMap>
          <Geographies geography={geoUrl}>
            {({ geographies }) =>
              geographies.map((geo) => {
                const countryCode = getCountryCode(geo.properties.name);
                const eventCount = eventsByCode[countryCode] || 0;
                const fillColor = getColor(eventCount);

                return (
                  <Geography
                    key={geo.rsmKey}
                    geography={geo}
                    onMouseEnter={() => setHoveredCountry({ name: geo.properties.name, code: countryCode, count: eventCount })}
                    onMouseLeave={() => setHoveredCountry(null)}
                    style={{
                      default: {
                        fill: fillColor,
                        stroke: '#fff',
                        strokeWidth: 0.75,
                        outline: 'none',
                        cursor: eventCount > 0 ? 'pointer' : 'default',
                        transition: 'all 250ms',
                      },
                      hover: {
                        fill: fillColor,
                        stroke: '#333',
                        strokeWidth: 1,
                        outline: 'none',
                        cursor: eventCount > 0 ? 'pointer' : 'default',
                        filter: 'brightness(0.9)',
                        transition: 'all 250ms',
                      },
                      pressed: {
                        fill: fillColor,
                        stroke: '#333',
                        strokeWidth: 1.5,
                        outline: 'none',
                      },
                    }}
                  />
                );
              })
            }
          </Geographies>
        </ComposableMap>
      </div>

      <div className="geomap-sidebar">
        <div className="geomap-legend">
          <h4>Top Countries</h4>
          {topCountries.map((item, idx) => (
            <div key={idx} className="legend-item">
              <span
                className="legend-color"
                style={{
                  backgroundColor: getColor(item.count),
                }}
              ></span>
              <span className="legend-label">{item.code} ({item.count})</span>
            </div>
          ))}
        </div>
        <div className="geomap-stats">
          <h4>Statistics</h4>
          <p><strong>Countries:</strong> {stats.countries}</p>
          <p><strong>Total Events:</strong> {stats.events}</p>
          {topCountries.length > 0 && (
            <p><strong>Top:</strong> {topCountries[0].code}</p>
          )}
        </div>
      </div>

      {hoveredCountry && (
        <div className="geomap-tooltip">
          <strong>{hoveredCountry.name}</strong>
          <br />
          Events: {hoveredCountry.count}
        </div>
      )}
    </div>
  );
}
